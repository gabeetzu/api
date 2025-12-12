const express = require('express');
const next = require('next');
const OpenAI = require('openai');

const dev = process.env.NODE_ENV !== 'production';
const app = next({ dev });
const handle = app.getRequestHandler();

const openai = new OpenAI({ apiKey: process.env.OPENAI_API_KEY });

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
  server.use(express.json());

  server.post('/api/ask', async (req, res) => {
    const { messages, mode } = req.body;

    if (!messages || !Array.isArray(messages)) {
      return res.status(400).json({ error: 'Invalid request format.' });
    }

    const conversation = [];
    if (mode && systemPrompts[mode]) {
      conversation.push({ role: 'system', content: systemPrompts[mode] });
    }

    conversation.push(...messages);

    try {
      const completion = await openai.chat.completions.create({
        model: 'gpt-4o-mini',
        messages: conversation,
      });

      const assistantReply = completion.choices[0]?.message?.content;
      res.json({ assistant: assistantReply });
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
