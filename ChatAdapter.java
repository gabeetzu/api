package com.secretele.gospodarului;

import android.content.Context;
import android.speech.tts.TextToSpeech;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageButton;
import android.widget.ImageView;
import android.widget.TextView;
import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;
import java.util.List;

public class ChatAdapter extends RecyclerView.Adapter<ChatAdapter.ChatViewHolder> {

    private static final int VIEW_TYPE_USER = 1;
    private static final int VIEW_TYPE_BOT = 2;
    private static final int VIEW_TYPE_LOADING = 3;

    private Context context;
    private List<ChatMessage> messages;
    private TextToSpeech textToSpeech;
    private int playingPosition = -1; // Only one message can play at a time

    public ChatAdapter(Context context, List<ChatMessage> messages, TextToSpeech textToSpeech) {
        this.context = context;
        this.messages = messages;
        this.textToSpeech = textToSpeech;
    }

    @Override
    public int getItemViewType(int position) {
        ChatMessage message = messages.get(position);
        if (message.isLoading()) {
            return VIEW_TYPE_LOADING;
        } else if (message.isUserMessage()) {
            return VIEW_TYPE_USER;
        } else {
            return VIEW_TYPE_BOT;
        }
    }

    @NonNull
    @Override
    public ChatViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        LayoutInflater inflater = LayoutInflater.from(context);
        View view;

        switch (viewType) {
            case VIEW_TYPE_USER:
                view = inflater.inflate(R.layout.item_message_user, parent, false);
                break;
            case VIEW_TYPE_BOT:
                view = inflater.inflate(R.layout.item_message_bot, parent, false);
                break;
            case VIEW_TYPE_LOADING:
                view = inflater.inflate(R.layout.item_message_loading, parent, false);
                break;
            default:
                view = inflater.inflate(R.layout.item_message_bot, parent, false);
                break;
        }

        return new ChatViewHolder(view, viewType);
    }

    @Override
    public void onBindViewHolder(@NonNull ChatViewHolder holder, int position) {
        ChatMessage message = messages.get(position);
        holder.bind(message, position);
    }

    @Override
    public int getItemCount() {
        return messages.size();
    }

    // ENHANCED: Better TTS control with callback
    public void stopTTS() {
        if (textToSpeech != null && textToSpeech.isSpeaking()) {
            textToSpeech.stop();
        }
        int oldPos = playingPosition;
        playingPosition = -1;
        if (oldPos != -1) {
            notifyItemChanged(oldPos);
        }
    }

    // NEW: Check if TTS is currently playing
    public boolean isTTSPlaying() {
        return textToSpeech != null && textToSpeech.isSpeaking();
    }

    class ChatViewHolder extends RecyclerView.ViewHolder {
        private TextView textMessage;
        private ImageView imageMessage;
        private ImageButton buttonSpeak, buttonStop; // Changed to ImageButton for better control
        private View loadingIndicator;
        private int viewType;

        public ChatViewHolder(@NonNull View itemView, int viewType) {
            super(itemView);
            this.viewType = viewType;

            textMessage = itemView.findViewById(R.id.textMessage);
            imageMessage = itemView.findViewById(R.id.imageMessage);
            buttonSpeak = itemView.findViewById(R.id.buttonSpeak);
            buttonStop = itemView.findViewById(R.id.buttonStop);
            loadingIndicator = itemView.findViewById(R.id.loadingIndicator);
        }

        public void bind(ChatMessage message, int position) {
            // Handle loading messages
            if (viewType == VIEW_TYPE_LOADING) {
                if (loadingIndicator != null) {
                    loadingIndicator.setVisibility(View.VISIBLE);
                }
                if (textMessage != null) {
                    textMessage.setText(message.getMessage());
                    textMessage.setVisibility(View.VISIBLE);
                }
                // Hide TTS buttons for loading messages
                if (buttonSpeak != null) buttonSpeak.setVisibility(View.GONE);
                if (buttonStop != null) buttonStop.setVisibility(View.GONE);
                return;
            }

            // Hide loading indicator for non-loading messages
            if (loadingIndicator != null) {
                loadingIndicator.setVisibility(View.GONE);
            }

            // Handle text message display
            if (textMessage != null && message.getMessage() != null && !message.getMessage().isEmpty()) {
                textMessage.setText(message.getMessage());
                textMessage.setVisibility(View.VISIBLE);
            } else if (textMessage != null) {
                textMessage.setVisibility(View.GONE);
            }

            // Handle image display
            if (imageMessage != null && message.hasImage()) {
                imageMessage.setImageBitmap(message.getImageBitmap());
                imageMessage.setVisibility(View.VISIBLE);
            } else if (imageMessage != null) {
                imageMessage.setVisibility(View.GONE);
            }

            // ENHANCED TTS Logic: Only for bot messages with manual control
            if (!message.isUserMessage() && !message.isLoading() &&
                    buttonSpeak != null && buttonStop != null &&
                    message.getMessage() != null && !message.getMessage().isEmpty()) {

                // Show appropriate button based on playing state
                if (playingPosition == position && isTTSPlaying()) {
                    buttonSpeak.setVisibility(View.GONE);
                    buttonStop.setVisibility(View.VISIBLE);
                } else {
                    buttonSpeak.setVisibility(View.VISIBLE);
                    buttonStop.setVisibility(View.GONE);
                }

                // SPEAK button click - START TTS
                buttonSpeak.setOnClickListener(v -> {
                    // Stop any currently playing TTS
                    stopTTS();

                    // Set this message as playing
                    playingPosition = position;
                    notifyItemChanged(position);

                    // Clean text for TTS and speak
                    String toSpeak = message.getCleanMessageForTTS();
                    if (!toSpeak.isEmpty() && textToSpeech != null) {
                        textToSpeech.speak(toSpeak, TextToSpeech.QUEUE_FLUSH, null, "botMsg" + position);

                        // Set up completion listener to reset buttons
                        textToSpeech.setOnUtteranceProgressListener(new android.speech.tts.UtteranceProgressListener() {
                            @Override
                            public void onStart(String utteranceId) {
                                // TTS started
                            }

                            @Override
                            public void onDone(String utteranceId) {
                                // TTS finished - reset buttons on UI thread
                                if (utteranceId.equals("botMsg" + position)) {
                                    ((android.app.Activity) context).runOnUiThread(() -> {
                                        playingPosition = -1;
                                        notifyItemChanged(position);
                                    });
                                }
                            }

                            @Override
                            public void onError(String utteranceId) {
                                // TTS error - reset buttons
                                if (utteranceId.equals("botMsg" + position)) {
                                    ((android.app.Activity) context).runOnUiThread(() -> {
                                        playingPosition = -1;
                                        notifyItemChanged(position);
                                    });
                                }
                            }
                        });
                    }
                });

                // STOP button click - STOP TTS
                buttonStop.setOnClickListener(v -> {
                    stopTTS();
                });

            } else {
                // Hide TTS buttons for user messages, loading messages, or empty messages
                if (buttonSpeak != null) buttonSpeak.setVisibility(View.GONE);
                if (buttonStop != null) buttonStop.setVisibility(View.GONE);
            }
        }
    }
}
