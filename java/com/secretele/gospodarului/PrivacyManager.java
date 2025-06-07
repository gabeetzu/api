package com.secretele.gospodarului;

import android.app.Activity;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.net.Uri;
import androidx.appcompat.app.AlertDialog;
import java.io.File;
import java.util.List;
import java.util.Iterator;
import android.widget.Toast;
public class PrivacyManager {

    private static final String PREF_NAME = "privacy_prefs";
    private static final String KEY_DISCLAIMER_SHOWN = "disclaimer_shown";
    private static final long DELETE_AFTER_DAYS = 7;
    private static final long MILLIS_PER_DAY = 24 * 60 * 60 * 1000L;

    private final Context context;
    private final SharedPreferences preferences;

    public PrivacyManager(Context context) {
        this.context = context.getApplicationContext();
        this.preferences = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
    }

    public void autoDeleteOldMessages(List<ChatMessage> messages) {
        long cutoff = System.currentTimeMillis() - (DELETE_AFTER_DAYS * MILLIS_PER_DAY);
        Iterator<ChatMessage> iterator = messages.iterator();
        while (iterator.hasNext()) {
            ChatMessage msg = iterator.next();
            // Fixed line below
            if (msg.getTimestamp().getTime() < cutoff) {
                iterator.remove();
            }
        }
    }

    public boolean shouldShowDisclaimer() {
        return !preferences.getBoolean(KEY_DISCLAIMER_SHOWN, false);
    }

    public void setDisclaimerShown() {
        preferences.edit().putBoolean(KEY_DISCLAIMER_SHOWN, true).apply();
    }

    // Updated privacy dialog with GDPR compliance
    public void showPrivacyDialog(Activity activity) {
        AlertDialog.Builder builder = new AlertDialog.Builder(activity);
        builder.setTitle("Confidențialitate și Prelucrare Date")
                .setMessage(
                        "Conform Regulamentului General privind Protecția Datelor (GDPR) și Legii 506/2004:\n\n" +
                                "1. Operator date: FUTURISTIC SRL (CUI 49637694)\n" +
                                "2. Scop prelucrare: Analiza plantelor prin Google Vision API și GPT-4o\n" +
                                "3. Date colectate: Imagini/text trimise, metadate tehnice\n" +
                                "4. Păstrare: Maxim 7 zile, după care ștergere automată\n\n" +
                                "5. Feedbackurile trimise voluntar (corecturi) sunt stocate anonim pentru îmbunătățirea aplicației.\n\n" +
                                "Poți revoca consimțământul oricând prin ștergerea datelor din aplicație."
                )
                .setPositiveButton("Accept", (dialog, which) -> {
                    setDisclaimerShown();
                    preferences.edit().putBoolean("gdpr_consent", true).apply(); // Add this line
                    dialog.dismiss();
                })
                .setNegativeButton("Refuz", (dialog, which) -> activity.finish())
                .setNeutralButton("Politica completă", (dialog, which) -> {
                    openPrivacyPolicy(activity);
                    dialog.dismiss();
                })
                .setCancelable(false)
                .show();
    }

    private void openPrivacyPolicy(Activity activity) {
        try {
            Intent browserIntent = new Intent(Intent.ACTION_VIEW,
                    Uri.parse("https://gospodapp.ro/politica-confidentialitate"));
            activity.startActivity(browserIntent);
        } catch (Exception e) {
            Toast.makeText(activity, "Politica nu este disponibilă momentan", Toast.LENGTH_SHORT).show();
        }
    }
    public boolean hasGDPRConsent() {
        return preferences.getBoolean("gdpr_consent", false); // Add this line
    }

    public void revokeConsent(Activity activity) {
        preferences.edit()
                .putBoolean("gdpr_consent", false)
                .putBoolean(KEY_DISCLAIMER_SHOWN, false)
                .apply();
        activity.recreate(); // Restart app to reset state
    }

    // Enhanced data deletion with file cleanup
    public void deleteAllUserData() {
        // Clear SharedPreferences
        preferences.edit().clear().apply();

        // Clear usage tracking data
        context.getSharedPreferences("usage_tracker", Context.MODE_PRIVATE)
                .edit().clear().apply();

        // Clear text size preferences
        context.getSharedPreferences("text_size_prefs", Context.MODE_PRIVATE)
                .edit().clear().apply();

        // Delete cached images
        deleteCacheFiles(context.getCacheDir());
        if (context.getExternalCacheDir() != null) {
            deleteCacheFiles(context.getExternalCacheDir());
        }
    }

    private void deleteCacheFiles(File cacheDir) {
        if (cacheDir != null && cacheDir.isDirectory()) {
            File[] files = cacheDir.listFiles();
            if (files != null) {
                for (File file : files) {
                    file.delete();
                }
            }
        }
    }

    // Rest of existing methods remain unchanged...
    // [Previous autoDeleteOldMessages, showPrivacyInfo, showDataDeletionDialog]
}
