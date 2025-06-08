# Codex Audit Instructions

Please audit this PHP + Android hybrid app for:

- ✅ PHP bugs, security holes, and performance issues
- ✅ Missing files (e.g. `daily_tips.json`, `bad_words.txt`)
- ✅ TTS triggering twice (conflict between onboarding.mp3 and TTS speak)
- ✅ UI input lockout if consent is accepted but not properly saved
- ✅ Ad banner visibility logic
- ✅ Premium logic that disables usage without proper upgrade handling
- ✅ Anything else that would break usage for elderly users offline

Also verify:
- `UsageTracker.java` correctly updates UI interactivity
- `MainActivity.java` doesn’t block camera/mic/text box after launch
- Missing assets don’t break app flow (`assets/` folder)

Feel free to auto-fix or comment directly in the source files.

This app is for elderly Romanian farmers. Prioritize UX, safety, and stability.
