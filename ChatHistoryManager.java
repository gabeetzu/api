package com.secretele.gospodarului;

import android.content.Context;
import android.content.SharedPreferences;
import com.google.gson.Gson;
import com.google.gson.reflect.TypeToken;
import java.lang.reflect.Type;
import java.util.ArrayList;
import java.util.List;

public class ChatHistoryManager {
    private static final String PREF_NAME = "chat_history";
    private static final String KEY_MESSAGES = "messages";
    private static final int MAX_MESSAGES = 100; // Limit to prevent excessive storage

    private final SharedPreferences sharedPreferences;
    private final Gson gson;

    public ChatHistoryManager(Context context) {
        this.sharedPreferences = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
        this.gson = new Gson();
    }

    public interface ChatHistoryCallback {
        void onSuccess(List<ChatMessage> messages);
        void onError(String error);
    }

    public void loadChatHistory(ChatHistoryCallback callback) {
        try {
            String json = sharedPreferences.getString(KEY_MESSAGES, "[]");
            Type listType = new TypeToken<List<ChatMessage>>(){}.getType();
            List<ChatMessage> messages = gson.fromJson(json, listType);

            if (messages == null) {
                messages = new ArrayList<>();
            }

            callback.onSuccess(messages);
        } catch (Exception e) {
            callback.onError("Failed to load chat history: " + e.getMessage());
        }
    }

    public void saveChatMessage(ChatMessage message) {
        try {
            loadChatHistory(new ChatHistoryCallback() {
                @Override
                public void onSuccess(List<ChatMessage> messages) {
                    // Add new message
                    messages.add(message);

                    // Keep only the last MAX_MESSAGES messages
                    if (messages.size() > MAX_MESSAGES) {
                        messages = messages.subList(messages.size() - MAX_MESSAGES, messages.size());
                    }

                    // Save back to preferences
                    String json = gson.toJson(messages);
                    sharedPreferences.edit()
                            .putString(KEY_MESSAGES, json)
                            .apply();
                }

                @Override
                public void onError(String error) {
                    // If loading fails, just save the single message
                    List<ChatMessage> messages = new ArrayList<>();
                    messages.add(message);
                    String json = gson.toJson(messages);
                    sharedPreferences.edit()
                            .putString(KEY_MESSAGES, json)
                            .apply();
                }
            });
        } catch (Exception e) {
            // Silent fail - chat history is not critical
        }
    }

    public void saveChatHistory(List<ChatMessage> messages) {
        try {
            // Keep only the last MAX_MESSAGES messages
            if (messages.size() > MAX_MESSAGES) {
                messages = messages.subList(messages.size() - MAX_MESSAGES, messages.size());
            }

            String json = gson.toJson(messages);
            sharedPreferences.edit()
                    .putString(KEY_MESSAGES, json)
                    .apply();
        } catch (Exception e) {
            // Silent fail - chat history is not critical
        }
    }

    public void clearChatHistory() {
        sharedPreferences.edit()
                .remove(KEY_MESSAGES)
                .apply();
    }

    public int getMessageCount() {
        try {
            String json = sharedPreferences.getString(KEY_MESSAGES, "[]");
            Type listType = new TypeToken<List<ChatMessage>>(){}.getType();
            List<ChatMessage> messages = gson.fromJson(json, listType);
            return messages != null ? messages.size() : 0;
        } catch (Exception e) {
            return 0;
        }
    }

    public List<ChatMessage> getLastMessages(int count) {
        try {
            String json = sharedPreferences.getString(KEY_MESSAGES, "[]");
            Type listType = new TypeToken<List<ChatMessage>>(){}.getType();
            List<ChatMessage> messages = gson.fromJson(json, listType);

            if (messages == null || messages.isEmpty()) {
                return new ArrayList<>();
            }

            int startIndex = Math.max(0, messages.size() - count);
            return messages.subList(startIndex, messages.size());
        } catch (Exception e) {
            return new ArrayList<>();
        }
    }

    public void exportChatHistory(ExportCallback callback) {
        loadChatHistory(new ChatHistoryCallback() {
            @Override
            public void onSuccess(List<ChatMessage> messages) {
                StringBuilder export = new StringBuilder();
                export.append("GOSPODAPP Chat History Export\n");
                export.append("===============================\n\n");

                for (ChatMessage message : messages) {
                    String sender = message.isUserMessage() ? "User" : "GospodApp";
                    export.append(sender).append(": ").append(message.getMessage()).append("\n\n");
                }

                callback.onSuccess(export.toString());
            }

            @Override
            public void onError(String error) {
                callback.onError(error);
            }
        });
    }

    public interface ExportCallback {
        void onSuccess(String exportedText);
        void onError(String error);
    }
}