const express = require('express');
const next = require('next');
const OpenAI = require('openai');
const rateLimit = require('express-rate-limit');

const dev = process.env.NODE_ENV !== 'production';
const app = next({ dev });
const handle = app.getRequestHandler();

const openAiApiKey = process.env.OPENAI_API_KEY;
const openai = openAiApiKey ? new OpenAI({ apiKey: openAiApiKey }) : null;

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

    if (!Array.isArray(messages)) {
      return res.status(400).json({ error: 'Invalid messages format.' });
    }

    const sanitizedMessages = messages.map((message) => ({
      role: message?.role,
      content: message?.content,
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

    if (!openai || !openAiApiKey) {
      return res.status(500).json({ error: 'Server misconfigured' });
    }

    try {
      const requestPayload = {
        model: 'gpt-4o-mini',
        input: sanitizedMessages,
        max_output_tokens: 700,
      };

      if (mode && systemPrompts[mode]) {
        requestPayload.instructions = systemPrompts[mode];
      }

      const response = await openai.responses.create(requestPayload);

      res.json({ assistant: response.output_text?.trim() ?? '' });
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
