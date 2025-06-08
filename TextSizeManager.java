package com.secretele.gospodarului;

import android.app.Activity;
import android.content.Context;
import android.content.SharedPreferences;
import android.util.TypedValue;
import android.widget.TextView;
import androidx.appcompat.app.AlertDialog;
import java.util.ArrayList;
import java.util.List;

public class TextSizeManager {
    
    private static final String PREF_NAME = "text_size_prefs";
    private static final String KEY_TEXT_SIZE = "text_size";
    private static final float DEFAULT_TEXT_SIZE = 26.0f;
    private static final float MIN_TEXT_SIZE = 18.0f;
    private static final float MAX_TEXT_SIZE = 32.0f;
    
    private Context context;
    private SharedPreferences preferences;
    private List<TextView> managedTextViews;
    
    public TextSizeManager(Context context) {
        this.context = context;
        this.preferences = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
        this.managedTextViews = new ArrayList<>();
    }
    
    public void registerTextView(TextView textView) {
        if (textView != null && !managedTextViews.contains(textView)) {
            managedTextViews.add(textView);
            applyTextSizeToView(textView);
        }
    }
    
    public void unregisterTextView(TextView textView) {
        managedTextViews.remove(textView);
    }
    
    public void applyTextSize() {
        float currentSize = getCurrentTextSize();
        for (TextView textView : managedTextViews) {
            if (textView != null) {
                applyTextSizeToView(textView);
            }
        }
    }
    
    private void applyTextSizeToView(TextView textView) {
        float currentSize = getCurrentTextSize();
        textView.setTextSize(TypedValue.COMPLEX_UNIT_SP, currentSize);
    }
    
    public float getCurrentTextSize() {
        return preferences.getFloat(KEY_TEXT_SIZE, DEFAULT_TEXT_SIZE);
    }
    
    public void setTextSize(float size) {
        float clampedSize = Math.max(MIN_TEXT_SIZE, Math.min(MAX_TEXT_SIZE, size));
        preferences.edit()
            .putFloat(KEY_TEXT_SIZE, clampedSize)
            .apply();
        applyTextSize();
    }
    
    public void increaseTextSize() {
        float currentSize = getCurrentTextSize();
        float newSize = currentSize + 2.0f;
        setTextSize(newSize);
    }
    
    public void decreaseTextSize() {
        float currentSize = getCurrentTextSize();
        float newSize = currentSize - 2.0f;
        setTextSize(newSize);
    }
    
    public void showTextSizeDialog() {
        if (!(context instanceof Activity)) {
            return;
        }
        
        Activity activity = (Activity) context;
        float currentSize = getCurrentTextSize();
        
        String[] sizeOptions = {
            "Mic (18sp)",
            "Normal (20sp)", 
            "Mare (24sp)",
            "Foarte mare (28sp)",
            "Extra mare (32sp)"
        };
        
        float[] sizeValues = {18.0f, 20.0f, 24.0f, 28.0f, 32.0f};
        
        // Find current selection
        int currentSelection = 2; // Default to "Mare"
        for (int i = 0; i < sizeValues.length; i++) {
            if (Math.abs(currentSize - sizeValues[i]) < 1.0f) {
                currentSelection = i;
                break;
            }
        }
        
        AlertDialog.Builder builder = new AlertDialog.Builder(activity);
        builder.setTitle("Mărime text")
            .setSingleChoiceItems(sizeOptions, currentSelection, (dialog, which) -> {
                setTextSize(sizeValues[which]);
                dialog.dismiss();
            })
            .setNegativeButton("Anulează", (dialog, which) -> dialog.dismiss());
        
        AlertDialog dialog = builder.create();
        dialog.show();
        
        // Apply current text size to dialog
        TextView titleView = dialog.findViewById(android.R.id.title);
        if (titleView != null) {
            titleView.setTextSize(TypedValue.COMPLEX_UNIT_SP, getCurrentTextSize());
        }
    }
    
    public boolean isTextSizeAtMinimum() {
        return getCurrentTextSize() <= MIN_TEXT_SIZE;
    }
    
    public boolean isTextSizeAtMaximum() {
        return getCurrentTextSize() >= MAX_TEXT_SIZE;
    }
    
    public void resetToDefault() {
        setTextSize(DEFAULT_TEXT_SIZE);
    }
    
    public String getTextSizeDescription() {
        float currentSize = getCurrentTextSize();
        if (currentSize <= 18.0f) {
            return "Mic";
        } else if (currentSize <= 20.0f) {
            return "Normal";
        } else if (currentSize <= 24.0f) {
            return "Mare";
        } else if (currentSize <= 28.0f) {
            return "Foarte mare";
        } else {
            return "Extra mare";
        }
    }
}