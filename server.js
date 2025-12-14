const express = require('express');
const next = require('next');
const OpenAI = require('openai');
const rateLimit = require('express-rate-limit');
const dns = require('dns').promises;
const net = require('net');
const { Agent } = require('undici');

const dev = process.env.NODE_ENV !== 'production';
const app = next({ dev });
const handle = app.getRequestHandler();

const openAiApiKey = process.env.OPENAI_API_KEY;
const openai = openAiApiKey ? new OpenAI({ apiKey: openAiApiKey }) : null;

const ALLOWED_MODES = new Set(['comfort', 'troubleshoot', 'game', 'chat']);

const URL_REGEX = /https?:\/\/[^\s]+/i;

const QUOTA_WINDOW_MS = Number(process.env.QUOTA_WINDOW_MS) || 60 * 60 * 1000;
const QUOTA_MAX_REQUESTS = Number(process.env.QUOTA_MAX_REQUESTS) || 200;
const quotaTracker = new Map();

const profanityList = ['damn', 'shit', 'fuck'];

const scrubContent = (text) => {
  if (!text) return text;

  let scrubbed = text;

  // Mask email addresses
  scrubbed = scrubbed.replace(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi, '[redacted-email]');

  // Mask phone numbers (very simple patterns)
  scrubbed = scrubbed.replace(/\b(?:\+?\d[\s-]?){7,15}\b/g, '[redacted-phone]');

  // Replace profanity
  profanityList.forEach((word) => {
    const regex = new RegExp(`\\b${word}\\b`, 'gi');
    scrubbed = scrubbed.replace(regex, '*'.repeat(word.length));
  });

  return scrubbed;
};

const checkQuota = (identifier) => {
  const now = Date.now();
  const record = quotaTracker.get(identifier);

  if (!record || now - record.windowStart > QUOTA_WINDOW_MS) {
    quotaTracker.set(identifier, { windowStart: now, count: 1 });
    return true;
  }

  if (record.count >= QUOTA_MAX_REQUESTS) {
    return false;
  }

  record.count += 1;
  return true;
};

const systemPrompts = {
  comfort:
    'You are a VR Comfort Coach, an expert in virtual reality health and safety. Provide friendly, helpful advice to keep VR users comfortable and prevent motion sickness or fatigue.',
  troubleshoot:
    'You are a VR Troubleshooting Assistant. You help users diagnose and fix issues with VR hardware or software in a step-by-step manner.',
  game:
    'You are a VR Game Recommendation Guru. You ask about user preferences and then suggest suitable VR games with a brief description.',
  chat:
    'You are a helpful AI assistant knowledgeable about VR. Engage in open conversation and answer questions about VR or any topic the user asks.',
};

const fetchWithTimeout = async (url, options = {}) => {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), options.timeoutMs || 5000);

  const fetchOptions = { ...options, signal: controller.signal };

  if (options.lookupHost) {
    const { hostname, address, family } = options.lookupHost;
    fetchOptions.dispatcher =
      options.dispatcher ||
      new Agent({
        connect: {
          lookup: (host, lookupOptions, callback) => {
            if (host === hostname) {
              callback(null, address, family || net.isIP(address));
              return;
            }

            dns
              .lookup(host, { all: false, family: lookupOptions?.family || 0 })
              .then((result) => callback(null, result.address, result.family))
              .catch((err) => callback(err));
          },
          servername: hostname,
        },
      });
  }

  delete fetchOptions.lookupHost;

  try {
    const response = await fetch(url, fetchOptions);
    return response;
  } finally {
    clearTimeout(timeoutId);
  }
};

const parseMetaTag = (html, name) => {
  const metaRegex = new RegExp(`<meta[^>]+(?:property|name)=["']${name}["'][^>]+content=["']([^"']+)["']`, 'i');
  const match = html.match(metaRegex);
  return match?.[1];
};

const classifyUrlType = (url) => {
  if (!url) return 'text';
  if (/youtube\.com|youtu\.be|vimeo\.com/i.test(url)) {
    return 'video';
  }
  return 'link';
};

const PREVIEW_HOST_ALLOWLIST = (process.env.PREVIEW_HOST_ALLOWLIST || '')
  .split(',')
  .map((host) => host.trim().toLowerCase())
  .filter(Boolean);

const isPrivateIp = (ip) => {
  if (!ip) return true;

  if (ip === '127.0.0.1' || ip === '::1') {
    return true;
  }

  if (net.isIP(ip) === 6) {
    const normalized = ip.toLowerCase();
    if (normalized.startsWith('fc') || normalized.startsWith('fd')) return true; // Unique local
    if (normalized.startsWith('fe80') || normalized.startsWith('fec0')) return true; // Link-local/site-local
  }

  const ipv4Match = ip.match(/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/);
  if (ipv4Match) {
    const [octet1, octet2] = ipv4Match.slice(1).map(Number);
    if (octet1 === 10) return true;
    if (octet1 === 127) return true;
    if (octet1 === 192 && octet2 === 168) return true;
    if (octet1 === 169 && octet2 === 254) return true;
    if (octet1 === 172 && octet2 >= 16 && octet2 <= 31) return true;
  }

  return false;
};

const isPrivateAddress = (hostname) => {
  if (!hostname) return true;

  const normalized = hostname.toLowerCase();
  if (net.isIP(normalized)) {
    return isPrivateIp(normalized);
  }

  if (normalized === 'localhost' || normalized === '127.0.0.1' || normalized === '::1') {
    return true;
  }

  // Block obvious internal hostnames
  if (/[.]local$|[.]internal$|[.]localhost$/.test(normalized)) {
    return true;
  }

  return false;
};

const resolvePublicDns = async (hostname) => {
  try {
    const records = await dns.lookup(hostname, { all: true });
    if (!records || records.length === 0) {
      return null;
    }

    const publicRecords = records.filter((record) => !isPrivateIp(record.address));
    if (publicRecords.length === 0) {
      return null;
    }

    return publicRecords;
  } catch (err) {
    console.warn(`DNS lookup failed for ${hostname}:`, err.message);
    return null;
  }
};

const resolveSafeUrl = async (rawUrl, maxRedirects = 3) => {
  let currentUrl;
  let currentLookupHost;
  try {
    currentUrl = new URL(rawUrl);
  } catch (err) {
    console.warn('Rejected preview URL:', err.message);
    return null;
  }

  if (!['http:', 'https:'].includes(currentUrl.protocol)) {
    return null;
  }

  for (let i = 0; i <= maxRedirects; i += 1) {
    const hostname = currentUrl.hostname.toLowerCase();

    let lookupRecords;

    if (PREVIEW_HOST_ALLOWLIST.length > 0) {
      if (!PREVIEW_HOST_ALLOWLIST.includes(hostname)) {
        return null;
      }
      lookupRecords = await resolvePublicDns(hostname);
      if (!lookupRecords) {
        return null;
      }
    } else if (isPrivateAddress(hostname)) {
      return null;
    } else {
      lookupRecords = await resolvePublicDns(hostname);
      if (!lookupRecords) {
        return null;
      }
    }

    currentLookupHost = lookupRecords?.[0]
      ? { hostname, address: lookupRecords[0].address, family: lookupRecords[0].family }
      : null;

    try {
      const headResponse = await fetchWithTimeout(currentUrl.toString(), {
        method: 'HEAD',
        redirect: 'manual',
        timeoutMs: 3000,
        lookupHost: currentLookupHost,
      });

      if (headResponse.status >= 300 && headResponse.status < 400 && headResponse.headers.has('location')) {
        const location = headResponse.headers.get('location');
        const nextUrl = new URL(location, currentUrl);
        if (nextUrl.protocol !== 'http:' && nextUrl.protocol !== 'https:') {
          return null;
        }
        currentUrl = nextUrl;
        continue;
      }

      return { url: currentUrl.toString(), lookupHost: currentLookupHost };
    } catch (err) {
      console.warn('Preview HEAD request failed:', err.message);
      return null;
    }
  }

  return null;
};

const fetchLinkPreview = async (url) => {
  const safeResult = await resolveSafeUrl(url);

  if (!safeResult) {
    return { url };
  }

  const { url: safeUrl, lookupHost: resolvedHost } = safeResult;

  try {
    const noEmbedResponse = await fetchWithTimeout(
      `https://noembed.com/embed?url=${encodeURIComponent(safeUrl)}`,
      { timeoutMs: 4000 },
    );

    if (noEmbedResponse.ok) {
      const data = await noEmbedResponse.json();
      if (data?.title || data?.thumbnail_url) {
        return {
          title: data.title,
          description: data.author_name,
          image: data.thumbnail_url,
          siteName: data.provider_name,
          url: safeUrl,
        };
      }
    }
  } catch (err) {
    console.warn('NoEmbed lookup failed:', err.message);
  }

  try {
    let currentUrl = safeUrl;
    let currentLookupHost = resolvedHost;
    let response;
    const previewFetchOptions = { timeoutMs: 4000, redirect: 'manual' };

    for (let i = 0; i < 2; i += 1) {
      response = await fetchWithTimeout(currentUrl, {
        ...previewFetchOptions,
        lookupHost: currentLookupHost,
      });

      if (response.status >= 300 && response.status < 400 && response.headers.has('location')) {
        const redirectedUrl = new URL(response.headers.get('location'), currentUrl).toString();
        const sanitizedRedirect = await resolveSafeUrl(redirectedUrl);

        if (!sanitizedRedirect) {
          throw new Error('Redirected to unsafe URL during preview fetch');
        }

        currentUrl = sanitizedRedirect.url;
        currentLookupHost = sanitizedRedirect.lookupHost;
        continue;
      }

      // Even though we explicitly request manual redirects, double check the response
      // did not already follow a redirect to an unsafe target.
      if (response.redirected) {
        throw new Error('Preview fetch unexpectedly followed a redirect');
      }

      break;
    }

    if (!response || !response.ok || (response.status >= 300 && response.status < 400)) {
      throw new Error(`Status ${response?.status}`);
    }
    const html = await response.text();

    return {
      title:
        parseMetaTag(html, 'og:title') ||
        parseMetaTag(html, 'twitter:title') ||
        html.match(/<title>([^<]+)<\/title>/i)?.[1],
      description:
        parseMetaTag(html, 'og:description') ||
        parseMetaTag(html, 'twitter:description'),
      image: parseMetaTag(html, 'og:image') || parseMetaTag(html, 'twitter:image'),
      siteName: parseMetaTag(html, 'og:site_name'),
      url: response.url || currentUrl,
    };
  } catch (err) {
    console.warn('Open Graph lookup failed:', err.message);
  }

  return { url };
};

const enrichMessage = async (message) => {
  const content = message?.content || '';
  const url = content.match(URL_REGEX)?.[0];

  if (!url) {
    return { type: 'text' };
  }

  const preview = await fetchLinkPreview(url);
  return {
    type: classifyUrlType(url),
    metadata: preview,
  };
};

app.prepare().then(() => {
  const server = express();
  server.set('trust proxy', 1);
  server.use(express.json());

  const askLimiter = rateLimit({
    windowMs: Number(process.env.RATE_LIMIT_WINDOW_MS) || 60_000,
    max: Number(process.env.RATE_LIMIT_MAX) || 20,
    standardHeaders: true,
    legacyHeaders: false,
    handler: (req, res) => {
      res
        .status(429)
        .json({ error: 'Rate limit exceeded. Please slow down.' });
    },
  });

  server.get('/api/healthz', (req, res) => {
    res.json({
      ok: true,
      hasOpenAIKey: Boolean(openAiApiKey),
    });
  });

  server.post('/api/ask', askLimiter, async (req, res) => {
    const { messages, mode } = req.body;

    if (mode && !ALLOWED_MODES.has(mode)) {
      console.warn(`Rejected request due to invalid mode: ${mode}`);
      return res.status(400).json({ error: 'Invalid mode specified.' });
    }

    const requesterId = `${req.ip || 'unknown-ip'}::${req.headers['x-user-id'] || 'anonymous'}`;
    if (!checkQuota(requesterId)) {
      console.warn(`Quota exceeded for ${requesterId}`);
      return res.status(429).json({ error: 'Usage quota exceeded. Please try later.' });
    }

    if (!Array.isArray(messages)) {
      return res.status(400).json({ error: 'Invalid messages format.' });
    }

    const MAX_MESSAGE_LENGTH = 4_000;
    const MAX_TOTAL_CONTENT_LENGTH = 20_000;
    const MAX_MESSAGES = 50;

    const sanitizedMessages = messages.map((message) => ({
      role: message?.role,
      content:
        typeof message?.content === 'string' ? message.content.trim() : message?.content,
      type: message?.type || 'text',
    }));

    const hasInvalidMessage = sanitizedMessages.some(
      (message) =>
        !message ||
        typeof message !== 'object' ||
        !['user', 'assistant'].includes(message.role) ||
        typeof message.content !== 'string',
    );

    if (hasInvalidMessage) {
      return res.status(400).json({ error: 'Invalid messages format.' });
    }

    if (sanitizedMessages.length > MAX_MESSAGES) {
      return res.status(400).json({ error: 'Too many messages.' });
    }

    const hasEmptyContent = sanitizedMessages.some((message) => message.content.length === 0);
    if (hasEmptyContent) {
      return res.status(400).json({ error: 'Message content cannot be empty.' });
    }

    const hasOversizedMessage = sanitizedMessages.some(
      (message) => message.content.length > MAX_MESSAGE_LENGTH,
    );

    if (hasOversizedMessage) {
      return res
        .status(400)
        .json({ error: `Message content exceeds ${MAX_MESSAGE_LENGTH} characters.` });
    }

    const totalContentLength = sanitizedMessages.reduce(
      (sum, message) => sum + message.content.length,
      0,
    );

    if (totalContentLength > MAX_TOTAL_CONTENT_LENGTH) {
      return res
        .status(400)
        .json({ error: 'Conversation is too large.' });
    }

    if (!openai || !openAiApiKey) {
      return res.status(500).json({ error: 'Server misconfigured' });
    }

    try {
      const combinedInput = sanitizedMessages.map((message) => `${message.role}: ${message.content}`).join('\n');
      const moderation = await openai.moderations.create({
        model: 'omni-moderation-latest',
        input: combinedInput,
      });

      const moderationResult = moderation.results?.[0];
      if (moderationResult?.flagged) {
        console.warn('Moderation flagged content', moderationResult.categories);
        return res.status(400).json({ error: 'Content violates usage policies.', categories: moderationResult.categories });
      }

      const scrubbedMessages = sanitizedMessages.map((message) => ({
        role: message.role,
        content: scrubContent(message.content),
      }));

      const requestPayload = {
        model: 'gpt-4o-mini',
        input: scrubbedMessages,
        max_output_tokens: 700,
      };

      if (mode && systemPrompts[mode]) {
        requestPayload.instructions = systemPrompts[mode];
      }

      const response = await openai.responses.create(requestPayload);
      const assistantContent = response.output_text?.trim() ?? '';

      const enrichedUserMessage = await enrichMessage(sanitizedMessages[sanitizedMessages.length - 1]);
      const assistantEnrichment = await enrichMessage({ content: assistantContent });

      res.json({
        assistant: {
          role: 'assistant',
          content: assistantContent,
          type: assistantEnrichment.type || 'text',
          metadata: assistantEnrichment.metadata,
        },
        enrichedUser: enrichedUserMessage,
      });
    } catch (err) {
      console.error('OpenAI API error:', err);
      res.status(500).json({ error: 'Failed to get response from AI.' });
    }
  });

  server.all('*', (req, res) => handle(req, res));

  const PORT = process.env.PORT || 3000;
  server.listen(PORT, (err) => {
    if (err) throw err;
    console.log(`> Server is running on http://localhost:${PORT}`);
  });
});
