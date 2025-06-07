package com.secretele.gospodarului;

import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.util.Base64;
import java.util.Date;

public class ChatMessage {
    private String message;
    private boolean isUserMessage;
    private boolean isLoading;
    private Date timestamp;
    private String messageType; // "text" or "image"
    private String imageData; // Base64 encoded image data if applicable

    // Constructor for text messages
    public ChatMessage(String message, boolean isUserMessage, boolean isLoading) {
        this.message = message;
        this.isUserMessage = isUserMessage;
        this.isLoading = isLoading;
        this.timestamp = new Date();
        this.messageType = "text";
        this.imageData = null;
    }

    // Constructor for image messages
    public ChatMessage(String message, boolean isUserMessage, boolean isLoading, String imageData) {
        this.message = message;
        this.isUserMessage = isUserMessage;
        this.isLoading = isLoading;
        this.timestamp = new Date();
        this.messageType = "image";
        this.imageData = imageData;
    }

    // Constructor for loading from persistence (with timestamp)
    public ChatMessage(String message, boolean isUserMessage, boolean isLoading,
                       String messageType, String imageData, long timestamp) {
        this.message = message;
        this.isUserMessage = isUserMessage;
        this.isLoading = isLoading;
        this.messageType = messageType != null ? messageType : "text";
        this.imageData = imageData;
        this.timestamp = new Date(timestamp);
    }

    // Primary Getters
    public String getMessage() {
        return message;
    }

    public boolean isUserMessage() {
        return isUserMessage;
    }

    public boolean isLoading() {
        return isLoading;
    }

    public Date getTimestamp() {
        return timestamp;
    }

    public String getMessageType() {
        return messageType;
    }

    public String getImageData() {
        return imageData;
    }

    public long getTimestampLong() {
        return timestamp.getTime();
    }

    // ADDED: Compatibility methods for MainActivity and ChatAdapter
    public String getText() {
        return getMessage(); // Alias for getMessage()
    }

    public boolean isUser() {
        return isUserMessage(); // Alias for isUserMessage()
    }

    // ADDED: Image bitmap conversion for ChatAdapter
    public Bitmap getImageBitmap() {
        if (imageData == null || imageData.isEmpty()) {
            return null;
        }

        try {
            byte[] decodedBytes = Base64.decode(imageData, Base64.DEFAULT);
            return BitmapFactory.decodeByteArray(decodedBytes, 0, decodedBytes.length);
        } catch (Exception e) {
            return null; // Return null if decoding fails
        }
    }

    // Setters
    public void setMessage(String message) {
        this.message = message;
    }

    public void setLoading(boolean loading) {
        this.isLoading = loading;
    }

    public void setUserMessage(boolean userMessage) {
        this.isUserMessage = userMessage;
    }

    public void setMessageType(String messageType) {
        this.messageType = messageType;
    }

    public void setImageData(String imageData) {
        this.imageData = imageData;
    }

    // Utility methods
    public boolean hasImage() {
        return imageData != null && !imageData.isEmpty();
    }

    public boolean isTextMessage() {
        return "text".equals(messageType);
    }

    public boolean isImageMessage() {
        return "image".equals(messageType);
    }

    public String getFormattedTimestamp() {
        java.text.SimpleDateFormat sdf = new java.text.SimpleDateFormat("HH:mm", java.util.Locale.getDefault());
        return sdf.format(timestamp);
    }

    public String getCleanMessageForTTS() {
        if (message == null) return "";

        String cleanText = message;

        // Remove asterisks and markdown
        cleanText = cleanText.replaceAll("\\*+", "");

        // Remove numbered lists (1., 2., 3., etc.)
        cleanText = cleanText.replaceAll("^\\d+\\.\\s*", "");

        // Remove bullet points
        cleanText = cleanText.replaceAll("^[-*+]\\s*", "");

        // Replace multiple spaces with single space
        cleanText = cleanText.replaceAll("\\s+", " ");

        // Remove special characters that TTS reads awkwardly
        cleanText = cleanText.replaceAll("[#@$%^&(){}\\[\\]|\\\\]", "");

        // Remove percentages in parentheses like (85%)
        cleanText = cleanText.replaceAll("\\s*\\(\\d+%\\)\\s*", " ");

        // Remove URLs
        cleanText = cleanText.replaceAll("http[s]?://\\S+", "");

        // Remove extra whitespace and trim
        cleanText = cleanText.replaceAll("\\s+", " ");

        return cleanText.trim();
    }

    // ADDED: Enhanced TTS method for better voice output
    public String getEnhancedTTSText() {
        String cleanText = getCleanMessageForTTS();

        // Replace common gardening abbreviations with full words
        cleanText = cleanText.replaceAll("\\bcm\\b", "centimetri");
        cleanText = cleanText.replaceAll("\\bmm\\b", "milimetri");
        cleanText = cleanText.replaceAll("\\bkg\\b", "kilograme");
        cleanText = cleanText.replaceAll("\\bg\\b", "grame");
        cleanText = cleanText.replaceAll("\\bl\\b", "litri");
        cleanText = cleanText.replaceAll("\\bml\\b", "mililitri");

        // Replace symbols with words
        cleanText = cleanText.replaceAll("°C", " grade Celsius");
        cleanText = cleanText.replaceAll("%", " procente");
        cleanText = cleanText.replaceAll("&", " și ");

        return cleanText;
    }

    @Override
    public boolean equals(Object obj) {
        if (this == obj) return true;
        if (obj == null || getClass() != obj.getClass()) return false;

        ChatMessage that = (ChatMessage) obj;

        // Handle null message comparison
        if (message == null && that.message != null) return false;
        if (message != null && !message.equals(that.message)) return false;

        return isUserMessage == that.isUserMessage &&
                isLoading == that.isLoading &&
                timestamp.equals(that.timestamp);
    }

    @Override
    public int hashCode() {
        int result = message != null ? message.hashCode() : 0;
        result = 31 * result + (isUserMessage ? 1 : 0);
        result = 31 * result + (isLoading ? 1 : 0);
        result = 31 * result + timestamp.hashCode();
        return result;
    }

    @Override
    public String toString() {
        String messagePreview = "";
        if (message != null) {
            messagePreview = message.length() > 50 ? message.substring(0, 50) + "..." : message;
        }

        return "ChatMessage{" +
                "message='" + messagePreview + '\'' +
                ", isUserMessage=" + isUserMessage +
                ", isLoading=" + isLoading +
                ", messageType='" + messageType + '\'' +
                ", hasImage=" + hasImage() +
                ", timestamp=" + getFormattedTimestamp() +
                '}';
    }
}
