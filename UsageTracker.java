package com.secretele.gospodarului;

import android.content.Context;
import android.content.SharedPreferences;
import android.provider.Settings;
import android.util.Log; // Added for logging
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;

public class UsageTracker {

    private static final String TAG = "UsageTracker"; // Added TAG for logging
    private static final String PREF_NAME = "usage_tracker";
    private static final String KEY_DAILY_TEXT_USAGE = "daily_text_usage";
    private static final String KEY_DAILY_IMAGE_USAGE = "daily_image_usage";
    private static final String KEY_LAST_RESET_DATE = "last_reset_date";
    private static final String KEY_IS_PREMIUM = "is_premium";
    private static final String KEY_EXTRA_QUESTIONS = "extra_questions";
    private static final String KEY_AD_COUNTER = "ad_counter";
    private static final String KEY_EXTRA_IMAGE_CREDITS = "extra_image_credits"; // Key for extra image credits

    private final SharedPreferences preferences;
    private final Context context;

    public UsageTracker(Context context) {
        this.context = context;
        this.preferences = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
        checkAndResetDailyUsage(); // Initial check
    }

    public String getDeviceHash() {
        String androidId = Settings.Secure.getString(
                context.getContentResolver(),
                Settings.Secure.ANDROID_ID
        );
        // It's generally better not to use device ID directly for privacy.
        // Consider a one-way hash of a unique app instance ID if needed for anonymous tracking.
        // For simplicity, keeping your current method but adding a note.
        return String.valueOf((androidId + "SALT_GARDEN_APP_UNIQUE_V1").hashCode()).replace("-", "A"); // Added salt, replaced "-"
    }

    private void checkAndResetDailyUsage() {
        String today = getCurrentDate();
        String lastResetDate = preferences.getString(KEY_LAST_RESET_DATE, "");

        if (!today.equals(lastResetDate)) {
            Log.d(TAG, "New day detected. Resetting daily usage and extra credits.");
            preferences.edit()
                    .putInt(KEY_DAILY_TEXT_USAGE, 0)
                    .putInt(KEY_DAILY_IMAGE_USAGE, 0)
                    .putInt(KEY_AD_COUNTER, 0)
                    .putInt(KEY_EXTRA_QUESTIONS, 0) // Reset extra text questions too if they are daily
                    .putInt(KEY_EXTRA_IMAGE_CREDITS, 0) // Reset extra image credits daily
                    .putString(KEY_LAST_RESET_DATE, today)
                    .apply();
        }
    }

    private String getCurrentDate() {
        return new SimpleDateFormat("yyyy-MM-dd", Locale.US).format(new Date());
    }

    // --- Text Limit Management ---
    public boolean canMakeTextAPICall() {
        checkAndResetDailyUsage(); // Ensure limits are fresh
        return getDailyTextUsage() < (getDailyTextLimit() + getExtraQuestions());
    }

    public void recordTextAPICall() {
        checkAndResetDailyUsage();
        int currentTextUsage = getDailyTextUsage();
        int currentExtraQuestions = getExtraQuestions();

        if (!isPremiumUser()) {
            incrementAdCounter();
        }

        if (currentExtraQuestions > 0) {
            preferences.edit().putInt(KEY_EXTRA_QUESTIONS, currentExtraQuestions - 1).apply();
            Log.d(TAG, "Used an extra text question. Remaining extra: " + (currentExtraQuestions - 1));
        } else {
            preferences.edit().putInt(KEY_DAILY_TEXT_USAGE, currentTextUsage + 1).apply();
            Log.d(TAG, "Recorded text API call. Daily usage: " + (currentTextUsage + 1));
        }
    }

    public int getDailyTextUsage() {
        // checkAndResetDailyUsage(); // No need to call again if canMakeTextAPICall already did
        return preferences.getInt(KEY_DAILY_TEXT_USAGE, 0);
    }

    public int getDailyTextLimit() {
        return isPremiumUser() ? 100 : 10;
    }

    // --- Image Limit Management ---
    public boolean canMakeImageAPICall() {
        checkAndResetDailyUsage(); // Ensure limits are fresh
        int extraCredits = preferences.getInt(KEY_EXTRA_IMAGE_CREDITS, 0);
        return getDailyImageUsage() < (getDailyImageLimit() + extraCredits);
    }

    public void recordImageAPICall() {
        checkAndResetDailyUsage();
        int currentImageUsage = getDailyImageUsage();
        int currentExtraImageCredits = preferences.getInt(KEY_EXTRA_IMAGE_CREDITS, 0);

        if (!isPremiumUser()) {
            incrementAdCounter();
        }

        if (currentExtraImageCredits > 0) {
            preferences.edit().putInt(KEY_EXTRA_IMAGE_CREDITS, currentExtraImageCredits - 1).apply();
            Log.d(TAG, "Used an extra image credit. Remaining extra: " + (currentExtraImageCredits - 1));
        } else {
            preferences.edit().putInt(KEY_DAILY_IMAGE_USAGE, currentImageUsage + 1).apply();
            Log.d(TAG, "Recorded image API call. Daily usage: " + (currentImageUsage + 1));
        }
    }

    public int getDailyImageUsage() {
        // checkAndResetDailyUsage(); // No need to call again if canMakeImageAPICall already did
        return preferences.getInt(KEY_DAILY_IMAGE_USAGE, 0);
    }

    public int getDailyImageLimit() {
        return isPremiumUser() ? 50 : 10;
    }

    // --- Ad Management ---
    public void incrementAdCounter() {
        int currentCounter = getAdCounter();
        preferences.edit().putInt(KEY_AD_COUNTER, currentCounter + 1).apply();
        Log.d(TAG, "Ad counter incremented to: " + (currentCounter + 1));
    }

    public boolean shouldShowInterstitialAd() {
        // This logic might need adjustment based on when you want to show interstitial ads.
        // Typically, after a certain number of actions (e.g., X messages sent).
        if (isPremiumUser()) return false;

        int adCount = getAdCounter();
        // Example: Show ad every 3 non-premium actions
        if (adCount >= 3) {
            preferences.edit().putInt(KEY_AD_COUNTER, 0).apply(); // Reset counter
            Log.d(TAG, "Interstitial ad should be shown.");
            return true;
        }
        return false;
    }

    private int getAdCounter() {
        return preferences.getInt(KEY_AD_COUNTER, 0);
    }

    // --- Extra Questions/Credits Management ---
    public int getExtraQuestions() {
        return preferences.getInt(KEY_EXTRA_QUESTIONS, 0);
    }

    public void addExtraQuestions(int count) { // For text questions
        int currentExtra = getExtraQuestions();
        preferences.edit().putInt(KEY_EXTRA_QUESTIONS, currentExtra + count).apply();
        Log.d(TAG, count + " extra text questions added. Total extra: " + (currentExtra + count));
    }

    // FIXED: Added missing addExtraImageCredits method
    public void addExtraImageCredits(int count) {
        int currentExtra = preferences.getInt(KEY_EXTRA_IMAGE_CREDITS, 0);
        preferences.edit().putInt(KEY_EXTRA_IMAGE_CREDITS, currentExtra + count).apply();
        Log.d(TAG, count + " extra image credits added. Total extra: " + (currentExtra + count));
    }

    // --- Premium Status ---
    public boolean isPremiumUser() {
        return preferences.getBoolean(KEY_IS_PREMIUM, false);
    }

    public void setPremiumUser(boolean isPremium) {
        preferences.edit().putBoolean(KEY_IS_PREMIUM, isPremium).apply();
        if (isPremium) {
            Log.i(TAG, "User upgraded to Premium.");
            // Optionally reset ad counter or give bonus credits on upgrade
            preferences.edit().putInt(KEY_AD_COUNTER, 0).apply();
        } else {
            Log.i(TAG, "User is no longer Premium.");
        }
    }

    // --- Utility and Status ---
    public void resetDailyUsage() { // More comprehensive reset
        String today = getCurrentDate();
        Log.d(TAG, "Manual reset of daily usage initiated for date: " + today);
        preferences.edit()
                .putInt(KEY_DAILY_TEXT_USAGE, 0)
                .putInt(KEY_DAILY_IMAGE_USAGE, 0)
                .putInt(KEY_AD_COUNTER, 0)
                .putInt(KEY_EXTRA_QUESTIONS, 0)
                .putInt(KEY_EXTRA_IMAGE_CREDITS, 0)
                .putString(KEY_LAST_RESET_DATE, today) // Ensure last reset date is updated
                .apply();
    }

    // FIXED: Added missing refreshUsageData method
    public void refreshUsageData() {
        // This method is called on onResume. Its main purpose is to trigger
        // checkAndResetDailyUsage if the day has changed while the app was paused.
        checkAndResetDailyUsage();
        Log.d(TAG, "Usage data refreshed. Daily limits checked.");
    }

    public String getUsageStatusMessage() {
        checkAndResetDailyUsage(); // Ensure data is fresh before creating status
        if (isPremiumUser()) {
            // Premium users might still have a "limit" for images if you set one, or it's truly unlimited
            int imageRemaining = getDailyImageLimit() + preferences.getInt(KEY_EXTRA_IMAGE_CREDITS, 0) - getDailyImageUsage();
            return "Premium: Întrebări nelimitate, " + Math.max(0, imageRemaining) + " analize foto rămase.";
        } else {
            int textRemaining = getDailyTextLimit() + getExtraQuestions() - getDailyTextUsage();
            int imageRemaining = getDailyImageLimit() + preferences.getInt(KEY_EXTRA_IMAGE_CREDITS, 0) - getDailyImageUsage();
            return "Gratuit: " + Math.max(0, textRemaining) + " întrebări, " + Math.max(0, imageRemaining) + " analize foto rămase.";
        }
    }

    public String getTextUsageStatusMessage() {
        checkAndResetDailyUsage();
        int remaining = getDailyTextLimit() + getExtraQuestions() - getDailyTextUsage();
        remaining = Math.max(0, remaining);

        if (isPremiumUser()) {
            return "Premium: Întrebări nelimitate disponibile!";
        } else {
            return remaining == 0 ? "Ați folosit toate întrebările gratuite! Urmăriți o reclamă pentru 3 extra." :
                    (remaining == 1 ? "Mai aveți 1 întrebare gratuită astăzi." :
                            "Mai aveți " + remaining + " întrebări gratuite astăzi.");
        }
    }

    public String getImageUsageStatusMessage() {
        checkAndResetDailyUsage();
        int remaining = getDailyImageLimit() + preferences.getInt(KEY_EXTRA_IMAGE_CREDITS, 0) - getDailyImageUsage();
        remaining = Math.max(0, remaining);

        if (isPremiumUser()) {
            return remaining == 0 ? "Ați folosit cele " + getDailyImageLimit() + " analize premium de astăzi!" :
                    (remaining == 1 ? "Mai aveți 1 analiză foto premium disponibilă astăzi." :
                            "Mai aveți " + remaining + " analize foto premium disponibile astăzi.");
        } else {
            return remaining == 0 ? "Ați folosit analiza foto gratuită! Urmăriți o reclamă pentru una extra." :
                    "Mai aveți " + remaining + " analiză foto gratuită disponibilă astăzi.";
        }
    }
}
