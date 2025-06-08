package com.secretele.gospodarului;

import android.Manifest;
import android.app.AlertDialog;
import android.app.AlarmManager;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.SharedPreferences;
import androidx.core.app.NotificationCompat;
import androidx.core.app.NotificationManagerCompat;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.content.res.AssetFileDescriptor;
import android.graphics.Bitmap;
import android.graphics.Matrix;
import android.net.Uri;
import android.util.Pair;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.provider.MediaStore;
import android.speech.RecognizerIntent;
import android.speech.tts.TextToSpeech;
import android.text.Editable;
import android.text.TextWatcher;
import android.util.Base64;
import android.util.Log;
import android.view.View;
import android.widget.EditText;
import android.widget.ImageButton;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.PopupMenu;
import android.widget.TextView;
import android.widget.Button;
import android.widget.Toast;
import android.media.MediaPlayer;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.appcompat.app.AppCompatActivity;
import androidx.cardview.widget.CardView;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.secretele.gospodarului.OfflineManager;
import com.secretele.gospodarului.OfflineContentProvider;

import com.google.android.gms.ads.AdRequest;
import com.google.android.gms.ads.AdView;
import com.google.android.gms.ads.LoadAdError;
import com.google.android.gms.ads.MobileAds;
import com.secretele.gospodarului.AdHelper;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import com.google.gson.JsonSyntaxException;

import org.tensorflow.lite.Interpreter;

import java.io.ByteArrayOutputStream;
import java.io.FileInputStream;
import java.io.IOException;
import java.nio.ByteBuffer;
import java.nio.ByteOrder;
import java.nio.channels.FileChannel;
import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;
import android.net.ConnectivityManager;
import android.net.NetworkInfo;

public class MainActivity extends AppCompatActivity implements TextToSpeech.OnInitListener {

    // ─── Constants ─────────────────────────────────────────────────────────────
    private static final String TAG = "MainActivity";
    private static final int REQUEST_IMAGE_CAPTURE = 1;
    private static final int REQUEST_SPEECH_INPUT = 3;
    private static final int REQUEST_CAMERA_PERMISSION = 100;
    private static final int REQUEST_STORAGE_PERMISSION = 101;
    private static final int REQUEST_MIC_PERMISSION = 102;
    private static final int REQUEST_LOCATION_PERMISSION = 103;

    private static final String PREFS = "gospod_prefs";
    private static final String KEY_NOTIF_ENABLED = "daily_tip_enabled";
    private static final String CHANNEL_ID = "daily_tip_channel";
    private static final String KEY_CHAT_TEXT_SIZE = "chat_text_size";
    private static final int NOTIF_ID = 1001;

    private static final String KEY_ONBOARDING_DONE = "onboarding_done";
    private static final int NOTIF_REQUEST_CODE = 2001;

    private static final String CNN_MODEL_FILE = "plant_model_5Classes.tflite";
    private static final int CNN_INPUT_SIZE = 224;
    private static final int NUM_CLASSES = 38;

    private static final String[] IMAGE_LOADING_STAGES = {
            "Pregătesc imaginea...", "Trimitem la expertul AI...",
            "Se analizează detaliile...", "Așteptăm recomandarea..."
    };

    // ─── UI Components ─────────────────────────────────────────────────────────
    private RecyclerView recyclerViewChat;
    private EditText editTextMessage;
    private ImageButton buttonSend, buttonCamera, buttonMicrophone, buttonMenu, buttonRemoveImage;
    private ImageView imagePreview, dailyTipArrow;
    private TextView textViewDailyTip;
    private TextView quotaView;
    private LinearLayout dailyTipHeader;
    private CardView imagePreviewContainer;
    private AdView adView;

    // ─── Core Components ───────────────────────────────────────────────────────
    private ChatAdapter chatAdapter;
    private List<ChatMessage> chatMessages;
    private ApiService apiService;
    private TextToSpeech textToSpeech;
    private UsageTracker usageTracker;
    private OfflineManager offlineManager;
    private OfflineContentProvider offlineContentProvider;
    private ChatHistoryManager chatHistoryManager;
    private AbuseFilter abuseFilter;
    private DailyTipProvider dailyTipProvider;
    private PrivacyManager privacyManager;
    private Interpreter tflite;

    // ─── State ─────────────────────────────────────────────────────────────────
    private Bitmap selectedImage;
    private boolean isTTSEnabled = false;
    private boolean isProcessingRequest = false;
    private float chatTextSizeSp;
    private boolean isLoadingAnimation = false;
    private int currentLoadingStage = 0;
    private Handler loadingHandler;

    // ─── onCreate() ─────────────────────────────────────────────────────────────
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);
        Log.d(TAG, "onCreate: Activity starting");

        // Privacy dialog (GDPR consent)
        privacyManager = new PrivacyManager(this);

        TextView gdprNotice = findViewById(R.id.gdpr_notice_text);
        Button gdprAccept = findViewById(R.id.gdpr_accept_button);
        View gdprBanner = findViewById(R.id.gdpr_banner);


        if (privacyManager.shouldShowDisclaimer()) {
            gdprNotice.setVisibility(View.VISIBLE);
            gdprAccept.setVisibility(View.VISIBLE);
            gdprBanner.setVisibility(View.VISIBLE);

            gdprAccept.setOnClickListener(v -> {
                privacyManager.setDisclaimerShown();
                gdprNotice.setVisibility(View.GONE);
                gdprAccept.setVisibility(View.GONE);
                gdprBanner.setVisibility(View.GONE);
            });
        }

        // Initialize TFLite model (in background to avoid blocking UI)
        new Thread(() -> {
            try {
                tflite = new Interpreter(loadModelFile());
                Log.d(TAG, "CNN model initialized successfully");
            } catch (Exception e) {
                Log.e(TAG, "Error initializing CNN model: " + e.getMessage());
            }
        }).start();

        initializeCoreComponents();
        initializeUIAndListeners();
        SharedPreferences prefs = getSharedPreferences(PREFS, MODE_PRIVATE);
        chatTextSizeSp = prefs.getFloat(KEY_CHAT_TEXT_SIZE, 16f);
        applyChatTextSize();
        updateGDPRUI();
        initializeAds();
        refreshQuota();
        loadInitialData();

        if (!prefs.getBoolean(KEY_ONBOARDING_DONE, false)) {
            showOnboarding();
            prefs.edit().putBoolean(KEY_ONBOARDING_DONE, true).apply();
        }

        createNotificationChannel();
        if (isNotificationsEnabled()) {
            scheduleDailyTipNotification();
        }

        if (getIntent().getBooleanExtra("show_tip", false)) {
            textViewDailyTip.setVisibility(View.VISIBLE);
            showDailyTip();
        }
    }

    // ─── loadModelFile() ────────────────────────────────────────────────────────
    private ByteBuffer loadModelFile() throws IOException {
        AssetFileDescriptor fileDescriptor = getAssets().openFd(CNN_MODEL_FILE);
        FileInputStream inputStream = new FileInputStream(fileDescriptor.getFileDescriptor());
        FileChannel fileChannel = inputStream.getChannel();
        return fileChannel.map(
                FileChannel.MapMode.READ_ONLY,
                fileDescriptor.getStartOffset(),
                fileDescriptor.getDeclaredLength()
        );
    }

    // ─── initializeCoreComponents() ─────────────────────────────────────────────
    private void initializeCoreComponents() {
        Log.d(TAG, "Initializing core components...");
        apiService = ApiClient.getApiService();
        usageTracker = new UsageTracker(this);
        offlineManager = new OfflineManager(this);
        offlineContentProvider = new OfflineContentProvider(this);
        chatHistoryManager = new ChatHistoryManager(this);
        abuseFilter = new AbuseFilter(this);
        dailyTipProvider = new DailyTipProvider(this);
        textToSpeech = new TextToSpeech(this, this);
        chatMessages = new ArrayList<>();
        loadingHandler = new Handler(Looper.getMainLooper());
    }

    // ─── initializeUIAndListeners() ──────────────────────────────────────────
    private void initializeUIAndListeners() {
        Log.d(TAG, "Initializing UI and Listeners...");
        recyclerViewChat = findViewById(R.id.recyclerViewChat);
        editTextMessage = findViewById(R.id.editTextMessage);
        buttonSend = findViewById(R.id.buttonSend);
        buttonCamera = findViewById(R.id.buttonCamera);
        buttonMicrophone = findViewById(R.id.imageButtonMic);
        buttonMenu = findViewById(R.id.buttonMenu);
        buttonRemoveImage = findViewById(R.id.buttonRemoveImage);
        imagePreview = findViewById(R.id.imagePreview);
        imagePreviewContainer = findViewById(R.id.imagePreviewContainer);
        textViewDailyTip = findViewById(R.id.textViewDailyTip);
        dailyTipHeader = findViewById(R.id.dailyTipHeader);
        dailyTipArrow = findViewById(R.id.dailyTipArrow);
        adView = findViewById(R.id.adView);
        quotaView = findViewById(R.id.quotaView);

        // Accessible UI tweaks
        textViewDailyTip.setTextSize(android.util.TypedValue.COMPLEX_UNIT_SP, 18);
        dailyTipHeader.setPadding(0, (int)(12 * getResources().getDisplayMetrics().density), 0, (int)(12 * getResources().getDisplayMetrics().density));

        // Chat RecyclerView
        chatAdapter = new ChatAdapter(this, chatMessages, textToSpeech);
        LinearLayoutManager layoutManager = new LinearLayoutManager(this);
        layoutManager.setStackFromEnd(true);
        recyclerViewChat.setLayoutManager(layoutManager);
        recyclerViewChat.setAdapter(chatAdapter);

        // Text watcher to toggle send button
        editTextMessage.addTextChangedListener(new TextWatcher() {
            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) { }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
                updateSendButtonVisibility();
            }

            @Override
            public void afterTextChanged(Editable s) { }
        });

        // Button click listeners
        buttonSend.setOnClickListener(v -> processUserInput());
        buttonCamera.setOnClickListener(v -> checkCameraPermissionAndOpenCamera());
        buttonMicrophone.setOnClickListener(v -> startVoiceInput());
        buttonMenu.setOnClickListener(this::showPopupMenu);
        buttonRemoveImage.setOnClickListener(v -> clearSelectedImage());
        dailyTipHeader.setOnClickListener(v -> toggleDailyTip());

        updateSendButtonVisibility();
        Log.d(TAG, "UI and Listeners Initialized.");
    }

    // ─── initializeAds() ───────────────────────────────────────────────────────
    private void initializeAds() {
        if (usageTracker != null && usageTracker.isPremiumUser()) {
            if (adView != null) adView.setVisibility(View.GONE);
            return;
        }
        MobileAds.initialize(this, initializationStatus -> { });
        AdRequest adRequest = new AdRequest.Builder().build();
        if (adView != null) adView.loadAd(adRequest);
        AdHelper.preload(this);
    }

    // ─── preprocessForCNN() ─────────────────────────────────────────────────────
    private ByteBuffer preprocessForCNN(Bitmap bitmap) {
        Bitmap resized = Bitmap.createScaledBitmap(bitmap, CNN_INPUT_SIZE, CNN_INPUT_SIZE, true);
        ByteBuffer inputBuffer = ByteBuffer.allocateDirect(CNN_INPUT_SIZE * CNN_INPUT_SIZE * 3 * 4);
        inputBuffer.order(ByteOrder.nativeOrder());

        int[] pixels = new int[CNN_INPUT_SIZE * CNN_INPUT_SIZE];
        resized.getPixels(pixels, 0, resized.getWidth(), 0, 0, resized.getWidth(), resized.getHeight());

        for (int pixel : pixels) {
            inputBuffer.putFloat(((pixel >> 16) & 0xFF) / 255.0f);
            inputBuffer.putFloat(((pixel >> 8) & 0xFF) / 255.0f);
            inputBuffer.putFloat((pixel & 0xFF) / 255.0f);
        }
        resized.recycle();
        return inputBuffer;
    }

    // ─── getCNNClassLabel() ─────────────────────────────────────────────────────
    public static final String[] CNN_LABELS = {
            "Apple___Apple_scab", "Apple___Black_rot", "Apple___Cedar_apple_rust",
            "Apple___healthy", "Blueberry___healthy", "Cherry_(including_sour)___Powdery_mildew",
            "Cherry_(including_sour)___healthy", "Corn_(maize)___Cercospora_leaf_spot",
            "Corn_(maize)___Common_rust_", "Corn_(maize)___Northern_Leaf_Blight",
            "Corn_(maize)___healthy", "Grape___Black_rot", "Grape___Esca_(Black_Measles)",
            "Grape___Leaf_blight_(Isariopsis_Leaf_Spot)", "Grape___healthy", "Gray_leaf_spot",
            "Orange___Haunglongbing_(Citrus_greening)", "Peach___Bacterial_spot", "Peach___healthy",
            "Pepper,_bell___Bacterial_spot", "Pepper,_bell___healthy", "Potato___Early_blight",
            "Potato___Late_blight", "Potato___healthy", "Raspberry___healthy", "Soybean___healthy",
            "Squash___Powdery_mildew", "Strawberry___Leaf_scorch", "Strawberry___healthy",
            "Tomato___Bacterial_spot", "Tomato___Early_blight", "Tomato___Late_blight",
            "Tomato___Leaf_Mold", "Tomato___Septoria_leaf_spot", "Tomato___Spider_mites",
            "Tomato___Target_Spot", "Tomato___Tomato_Yellow_Leaf_Curl_Virus", "Tomato___Tomato_mosaic_virus"
    };

    private String getCNNClassLabel(int index) {
        return (index >= 0 && index < CNN_LABELS.length) ? CNN_LABELS[index] : "Unknown";
    }
    // ─── handleApiCall() ─────────────────────────────────────────────────────────
    private void handleApiCall(@Nullable String userInput, @Nullable Bitmap image) {
        isProcessingRequest = true;
        String loadingMsg = (image != null ? "Analizez imaginea..." : "Procesez întrebarea...");
        addLoadingMessageToChat(loadingMsg);
        if (image != null) startImageLoadingAnimation();

        android.net.ConnectivityManager cm = (android.net.ConnectivityManager) getSystemService(CONNECTIVITY_SERVICE);
        android.net.NetworkInfo ni = cm != null ? cm.getActiveNetworkInfo() : null;
        boolean offline = ni == null || !ni.isConnected();

        new Thread(() -> {
            try {
                JsonObject request = new JsonObject();
                // If there's an image, compress, encode and run local CNN
                if (image != null) {
                    Bitmap compressed = compressImage(image);
                    ByteArrayOutputStream baos = new ByteArrayOutputStream();
                    compressed.compress(Bitmap.CompressFormat.JPEG, 70, baos);
                    String base64Image = Base64.encodeToString(baos.toByteArray(), Base64.DEFAULT);
                    request.addProperty("image", base64Image);
                    baos.close();

                    Pair<String, Float> cnn = ImageProcessor.getDiagnosisAndConfidence(MainActivity.this, compressed);
                    if (cnn.first != null) {
                        request.addProperty("diagnosis", cnn.first);
                        request.addProperty("confidence", cnn.second);
                    }
                }
                // If there's text
                if (userInput != null && !userInput.isEmpty()) {
                    request.addProperty("message", userInput);
                }
                // Add device hash
                request.addProperty("device_hash", usageTracker.getDeviceHash());

                String weather = fetchWeather();
                if (weather != null) {
                    request.addProperty("weather", weather);
                }

                if (offline) {
                    offlineManager.enqueue(request);
                    runOnUiThread(() -> {
                        removeLoadingMessageFromChat();
                        clearSelectedImage();
                        addBotMessageToChat("Conexiune indisponibilă. Întrebarea a fost salvată.");
                        showOfflineContent();
                    });
                    return;
                }

                runOnUiThread(() -> {
                    apiService.processRequest(request).enqueue(new Callback<JsonObject>() {
                        @Override
                        public void onResponse(@NonNull Call<JsonObject> call, @NonNull Response<JsonObject> response) {
                            isProcessingRequest = false;
                            if (image != null) stopImageLoadingAnimation();
                            removeLoadingMessageFromChat();
                            clearSelectedImage();

                            if (response.isSuccessful() && response.body() != null) {
                                JsonObject body = response.body();
                                Log.d(TAG, "API response: " + body);
                                try {
                                    if (body.has("success") && body.get("success").getAsBoolean()) {
                                        String botResponse = safeGetResponse(body);
                                        addBotMessageToChat(botResponse);
                                        if (offlineManager != null) offlineManager.cacheResponse(botResponse);
                                        usageTracker.recordTextAPICall();
                                        if (image != null) usageTracker.recordImageAPICall();
                                        refreshQuota();
                                    } else {
                                        String error = body.has("error") && body.get("error").isJsonPrimitive()
                                                ? body.get("error").getAsString()
                                                : "Eroare necunoscută";
                                        addBotMessageToChat("⚠️ " + error);
                                    }
                                } catch (Exception e) {
                                    Log.e(TAG, "Error processing API response: " + e.getMessage());
                                    addBotMessageToChat("⚠️ Eroare procesare răspuns");
                                }
                            } else {
                                Log.e(TAG, "API error code: " + response.code() + ", message: " + response.message());
                                handleApiError(response, "api");
                            }
                        }

                        @Override
                        public void onFailure(@NonNull Call<JsonObject> call, @NonNull Throwable t) {
                            isProcessingRequest = false;
                            if (image != null) stopImageLoadingAnimation();
                            removeLoadingMessageFromChat();
                            clearSelectedImage();
                            Log.e(TAG, "API Failure: " + t.getMessage(), t);
                            addBotMessageToChat("⚠️ Eroare conexiune: " + t.getMessage());
                        }
                    });
                });
            } catch (Exception e) {
                runOnUiThread(() -> {
                    Log.e(TAG, "API call error: " + e.getMessage());
                    addBotMessageToChat("⚠️ Eroare procesare: " + e.getMessage());
                    if (image != null) stopImageLoadingAnimation();
                    clearSelectedImage();
                    isProcessingRequest = false;
                    removeLoadingMessageFromChat();
                });
            }
        }).start();
    }

    // ─── safeGetResponse() ───────────────────────────────────────────────────────
    private String safeGetResponse(JsonObject body) {
        try {
            JsonElement respElem = body.get("response");
            if (respElem == null) {
                Log.e(TAG, "Missing 'response' field in: " + body);
                return "Răspuns invalid de la server";
            }
            if (respElem.isJsonPrimitive()) {
                return respElem.getAsString();
            } else {
                JsonObject respObj = respElem.getAsJsonObject();
                if (respObj.has("text")) {
                    return respObj.get("text").getAsString();
                }
                if (respObj.has("diagnosis") && respObj.has("treatment")) {
                    return respObj.get("diagnosis").getAsString() + "\n\n" + respObj.get("treatment").getAsString();
                }
                return respObj.toString();
            }
        } catch (Exception e) {
            Log.e(TAG, "Error parsing response: " + e.getMessage() + ", body: " + body);
            return "Eroare la interpretarea răspunsului";
        }
    }

    // ─── compressImage() ─────────────────────────────────────────────────────────
    private Bitmap compressImage(Bitmap original) {
        if (original == null) return null;
        int maxDim = 1024;
        float scale = Math.min(
                (float) maxDim / original.getWidth(),
                (float) maxDim / original.getHeight()
        );
        if (scale >= 1f) return original;
        Matrix matrix = new Matrix();
        matrix.postScale(scale, scale);
        Bitmap resized = Bitmap.createBitmap(
                original, 0, 0,
                original.getWidth(), original.getHeight(),
                matrix, true);
        original.recycle();
        return resized;
    }

    // ─── processUserInput() ─────────────────────────────────────────────────────
    private void processUserInput() {
        if (!privacyManager.hasGDPRConsent()) {
            Toast.makeText(this, "Acceptă politica de confidențialitate mai întâi", Toast.LENGTH_SHORT).show();
            return;
        }
        if (isProcessingRequest) {
            Toast.makeText(this, "Așteaptă finalizarea răspunsului anterior.", Toast.LENGTH_SHORT).show();
            return;
        }

        String userInput = editTextMessage.getText().toString().trim();
        if (userInput.isEmpty() && selectedImage == null) {
            Toast.makeText(this, "Scrie un mesaj sau selectează o imagine.", Toast.LENGTH_SHORT).show();
            return;
        }
        if (abuseFilter.isAbusive(userInput)) {
            Toast.makeText(this, "Mesajul conține cuvinte nepermise.", Toast.LENGTH_SHORT).show();
            return;
        }
        boolean isPremium = usageTracker.isPremiumUser();
        if (!isPremium && usageTracker.getTextCount() >= UsageTracker.FREE_TEXT_LIMIT && selectedImage == null) {
            showUpgradeDialog("Ai atins limita zilnică de 3 întrebări.");
            return;
        }
        int remainingPhotos = UsageTracker.FREE_PHOTO_LIMIT + usageTracker.getRewardedTokens() - usageTracker.getPhotoCount();
        if (!isPremium && selectedImage != null && remainingPhotos <= 0) {
            showRewardedAdOrUpgrade();
            return;
        }

        addUserMessageToChat(userInput, selectedImage);
        editTextMessage.setText("");

        // Unified API call
        String finalInput = userInput.isEmpty() && selectedImage != null
                ? "Analizează această imagine."
                : userInput;
        handleApiCall(finalInput, selectedImage);
    }

    // ─── addUserMessageToChat() ────────────────────────────────────────────────
    private void addUserMessageToChat(String message, Bitmap image) {
        String base64Image = null;
        if (image != null && !image.isRecycled()) {
            try {
                Bitmap copy = image.copy(
                        image.getConfig() != null ? image.getConfig() : Bitmap.Config.ARGB_8888, false
                );
                ByteArrayOutputStream baos = new ByteArrayOutputStream();
                copy.compress(Bitmap.CompressFormat.JPEG, 80, baos);
                base64Image = Base64.encodeToString(baos.toByteArray(), Base64.DEFAULT);
                copy.recycle();
                baos.close();
            } catch (Exception e) {
                Log.e(TAG, "Failed to compress image for chat: " + e.getMessage());
            }
        }
        // Add ChatMessage
        ChatMessage msg = (base64Image != null)
                ? new ChatMessage(message, true, false, base64Image)
                : new ChatMessage(message, true, false);
        chatMessages.add(msg);
        chatAdapter.notifyItemInserted(chatMessages.size() - 1);
        scrollToBottom();

        if (chatHistoryManager != null) {
            chatHistoryManager.saveChatMessage(msg);
        }
    }

    // ─── addBotMessageToChat() ─────────────────────────────────────────────────
    private void addBotMessageToChat(String message) {
        ChatMessage botMsg = new ChatMessage(message, false, false);
        chatMessages.add(botMsg);
        chatAdapter.notifyItemInserted(chatMessages.size() - 1);
        scrollToBottom();
        speakBotMessage(message);
        if (chatHistoryManager != null) {
            chatHistoryManager.saveChatMessage(botMsg);
        }
    }

    // ─── addLoadingMessageToChat() ─────────────────────────────────────────────
    private void addLoadingMessageToChat(String message) {
        ChatMessage loadingMsg = new ChatMessage(message, false, true);
        chatMessages.add(loadingMsg);
        chatAdapter.notifyItemInserted(chatMessages.size() - 1);
        scrollToBottom();
    }

    // ─── removeLoadingMessageFromChat() ────────────────────────────────────────
    private void removeLoadingMessageFromChat() {
        for (int i = chatMessages.size() - 1; i >= 0; i--) {
            if (chatMessages.get(i).isLoading()) {
                chatMessages.remove(i);
                chatAdapter.notifyItemRemoved(i);
                break;
            }
        }
    }

    // ─── scrollToBottom() ───────────────────────────────────────────────────────
    private void scrollToBottom() {
        if (!chatMessages.isEmpty()) {
            recyclerViewChat.scrollToPosition(chatMessages.size() - 1);
        }
    }
    // ─── Permissions and Intent Helpers ─────────────────────────────────────────
    private void checkCameraPermissionAndOpenCamera() {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA)
                != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(
                    this, new String[]{Manifest.permission.CAMERA}, REQUEST_CAMERA_PERMISSION
            );
        } else {
            openCamera();
        }
    }

    private void openCamera() {
        Intent intent = new Intent(MediaStore.ACTION_IMAGE_CAPTURE);
        try {
            startActivityForResult(intent, REQUEST_IMAGE_CAPTURE);
        } catch (Exception e) {
            Toast.makeText(this, "Eroare la deschiderea camerei.", Toast.LENGTH_SHORT).show();
        }
    }

    private void startVoiceInput() {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.RECORD_AUDIO)
                != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(
                    this, new String[]{Manifest.permission.RECORD_AUDIO}, REQUEST_MIC_PERMISSION
            );
        } else {
            openSpeechRecognizer();
        }
    }

    private void openSpeechRecognizer() {
        Intent intent = new Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH);
        intent.putExtra(RecognizerIntent.EXTRA_LANGUAGE_MODEL, RecognizerIntent.LANGUAGE_MODEL_FREE_FORM);
        intent.putExtra(RecognizerIntent.EXTRA_LANGUAGE, "ro-RO");
        intent.putExtra(RecognizerIntent.EXTRA_PROMPT, "Vorbește acum...");
        try {
            startActivityForResult(intent, REQUEST_SPEECH_INPUT);
        } catch (Exception e) {
            Toast.makeText(this, "Recunoașterea vocală nu este disponibilă.", Toast.LENGTH_SHORT).show();
        }
    }

    // ─── onRequestPermissionsResult() ─────────────────────────────────────────
    @Override
    public void onRequestPermissionsResult(int reqCode, @NonNull String[] perms, @NonNull int[] results) {
        super.onRequestPermissionsResult(reqCode, perms, results);
        if (results.length > 0 && results[0] == PackageManager.PERMISSION_GRANTED) {
            if (reqCode == REQUEST_CAMERA_PERMISSION) openCamera();
            else if (reqCode == REQUEST_MIC_PERMISSION) openSpeechRecognizer();
        } else {
            String pName = (reqCode == REQUEST_CAMERA_PERMISSION) ? "cameră"
                    : (reqCode == REQUEST_STORAGE_PERMISSION) ? "stocare"
                    : "microfon";
            Toast.makeText(this, "Permisiunea pentru " + pName + " este necesară.", Toast.LENGTH_LONG).show();
        }
    }

    // ─── onActivityResult() ─────────────────────────────────────────────────────
    @Override
    protected void onActivityResult(int reqCode, int resCode, @Nullable Intent data) {
        super.onActivityResult(reqCode, resCode, data);
        if (resCode == RESULT_OK && data != null) {
            if (reqCode == REQUEST_IMAGE_CAPTURE) {
                Bundle extras = data.getExtras();
                if (extras != null) {
                    selectedImage = (Bitmap) extras.get("data");
                    showImagePreviewUI(selectedImage);
                }
            } else if (reqCode == REQUEST_SPEECH_INPUT) {
                ArrayList<String> res = data.getStringArrayListExtra(RecognizerIntent.EXTRA_RESULTS);
                if (res != null && !res.isEmpty()) {
                    editTextMessage.setText(res.get(0));
                    editTextMessage.setSelection(editTextMessage.getText().length());
                }
            }
        }
    }

    // ─── showImagePreviewUI() ───────────────────────────────────────────────────
    private void showImagePreviewUI(Bitmap bitmap) {
        if (bitmap != null) {
            imagePreview.setImageBitmap(bitmap);
            imagePreviewContainer.setVisibility(View.VISIBLE);
            updateSendButtonVisibility();
            Toast.makeText(this, "Imagine selectată. Adaugă un mesaj dacă dorești.", Toast.LENGTH_LONG).show();
        }
    }

    private void clearSelectedImage() {
        selectedImage = null;
        imagePreview.setImageBitmap(null);
        imagePreviewContainer.setVisibility(View.GONE);
        updateSendButtonVisibility();
    }

    // ─── handleApiError() ───────────────────────────────────────────────────────
    private void handleApiError(Response<JsonObject> response, String type) {
        String errorMsg = "Eroare necunoscută";
        try {
            if (response.errorBody() != null) {
                String errorBody = response.errorBody().string();
                try {
                    JsonObject errorObj = JsonParser.parseString(errorBody).getAsJsonObject();
                    if (errorObj.has("error") && errorObj.get("error").isJsonPrimitive()) {
                        errorMsg = errorObj.get("error").getAsString();
                    }
                } catch (JsonSyntaxException e) {
                    errorMsg = errorBody;
                }
            }
        } catch (IOException e) {
            Log.e(TAG, "Error parsing error body: " + e.getMessage());
        }
        addBotMessageToChat("⚠️ " + errorMsg);
    }

    // ─── Loading Animation Helpers ──────────────────────────────────────────────
    private void startImageLoadingAnimation() {
        isLoadingAnimation = true;
        currentLoadingStage = 0;
        updateImageLoadingMessage();
    }

    private void updateImageLoadingMessage() {
        if (!isLoadingAnimation || currentLoadingStage >= IMAGE_LOADING_STAGES.length) return;
        for (int i = chatMessages.size() - 1; i >= 0; i--) {
            if (chatMessages.get(i).isLoading()) {
                chatMessages.get(i).setMessage(IMAGE_LOADING_STAGES[currentLoadingStage]);
                chatAdapter.notifyItemChanged(i);
                break;
            }
        }
        currentLoadingStage++;
        if (isLoadingAnimation && currentLoadingStage < IMAGE_LOADING_STAGES.length) {
            if (loadingHandler != null) {
                loadingHandler.postDelayed(this::updateImageLoadingMessage, 2500);
            }
        }
    }

    private void stopImageLoadingAnimation() {
        isLoadingAnimation = false;
        if (loadingHandler != null) loadingHandler.removeCallbacksAndMessages(null);
        removeLoadingMessageFromChat();
    }
    // ─── loadInitialData() ──────────────────────────────────────────────────────
    private void loadInitialData() {
        Log.d(TAG, "Loading initial data (history and daily tip)...");

        if (!privacyManager.hasGDPRConsent()) {
            addWelcomeMessage();
            return;
        }

        chatHistoryManager.loadChatHistory(new ChatHistoryManager.ChatHistoryCallback() {
            @Override
            public void onSuccess(List<ChatMessage> messages) {
                if (messages != null && !messages.isEmpty()) {
                    chatMessages.addAll(messages);
                    chatAdapter.notifyDataSetChanged();
                    scrollToBottom();
                } else if (chatMessages.isEmpty()) {
                    addWelcomeMessage();
                }
            }

            @Override
            public void onError(String error) {
                if (chatMessages.isEmpty()) addWelcomeMessage();
            }
        });
        showDailyTip();
    }

    // ─── addWelcomeMessage() ────────────────────────────────────────────────────
    private void addWelcomeMessage() {
        String welcome = "Bună! Sunt asistentul tău pentru grădinărit. Trimite o poză sau scrie o întrebare! 🌱";
        if (chatMessages.isEmpty()) {
            ChatMessage welcomeMsg = new ChatMessage(welcome, false, false);
            chatMessages.add(welcomeMsg);
            chatAdapter.notifyItemInserted(0);
            recyclerViewChat.scrollToPosition(0);
            chatHistoryManager.saveChatMessage(welcomeMsg);
        }
    }

    // ─── showPopupMenu() ─────────────────────────────────────────────────────────
    // ─── showPopupMenu() ─────────────────────────────────────────────────────────
    private void showPopupMenu(View view) {
        PopupMenu popup = new PopupMenu(this, view);
        popup.getMenuInflater().inflate(R.menu.main_menu, popup.getMenu());
        popup.setOnMenuItemClickListener(item -> {
            int id = item.getItemId();
            if (id == R.id.action_clear_chat) {
                confirmClearChatHistory();
                return true;
            } else if (id == R.id.action_settings) {
                showSettingsDialog(); // now contains increase/decrease text + others
                return true;
            } else if (id == R.id.action_help) {
                showOnboarding();
                return true;
            } else if (id == R.id.action_social) {
                showSocialLinksDialog();
                return true;
            } else {
                return false;
            }
        });
        popup.show();
    }
    private void openLegalDocument(String url) {
        try {
            startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url)));
        } catch (Exception e) {
            Toast.makeText(this, "Document indisponibil", Toast.LENGTH_SHORT).show();
        }
    }

    // ─── confirmClearChatHistory() ─────────────────────────────────────────────
    private void confirmClearChatHistory() {
        new AlertDialog.Builder(this)
                .setTitle("Șterge Conversația")
                .setMessage("Ești sigur?")
                .setPositiveButton("Șterge", (dialog, which) -> {
                    chatMessages.clear();
                    chatAdapter.notifyDataSetChanged();
                    chatHistoryManager.clearChatHistory();
                    addWelcomeMessage();
                    Toast.makeText(this, "Conversația a fost ștearsă.", Toast.LENGTH_SHORT).show();
                })
                .setNegativeButton("Anulează", null)
                .show();
    }

    // ─── Daily Tip Expand/Collapse ───────────────────────────────────────────────
    private void toggleDailyTip() {
        if (textViewDailyTip.getVisibility() == View.GONE) {
            textViewDailyTip.setVisibility(View.VISIBLE);
            dailyTipArrow.setRotation(180);
            showDailyTip();
        } else {
            textViewDailyTip.setVisibility(View.GONE);
            dailyTipArrow.setRotation(0);
        }
    }

    private void applyChatTextSize() {
        editTextMessage.setTextSize(android.util.TypedValue.COMPLEX_UNIT_SP, chatTextSizeSp);
        int count = recyclerViewChat.getChildCount();
        for (int i = 0; i < count; i++) {
            applyTextSizeToView(recyclerViewChat.getChildAt(i));
        }
    }

    private void applyTextSizeToView(View view) {
        if (view instanceof android.widget.TextView) {
            ((android.widget.TextView) view).setTextSize(android.util.TypedValue.COMPLEX_UNIT_SP, chatTextSizeSp);
        } else if (view instanceof android.view.ViewGroup) {
            android.view.ViewGroup vg = (android.view.ViewGroup) view;
            for (int i = 0; i < vg.getChildCount(); i++) {
                applyTextSizeToView(vg.getChildAt(i));
            }
        }
    }

    private void increaseTextSize() {
        chatTextSizeSp += 2f;
        if (chatTextSizeSp > 30f) chatTextSizeSp = 30f;
        getSharedPreferences(PREFS, MODE_PRIVATE).edit()
                .putFloat(KEY_CHAT_TEXT_SIZE, chatTextSizeSp).apply();
        applyChatTextSize();
    }

    private void decreaseTextSize() {
        chatTextSizeSp -= 2f;
        if (chatTextSizeSp < 14f) chatTextSizeSp = 14f;
        getSharedPreferences(PREFS, MODE_PRIVATE).edit()
                .putFloat(KEY_CHAT_TEXT_SIZE, chatTextSizeSp).apply();
        applyChatTextSize();
    }

    private void updateGDPRUI() {
        boolean consent = privacyManager.hasGDPRConsent();
        editTextMessage.setEnabled(consent);
        buttonCamera.setEnabled(consent);
        buttonMicrophone.setEnabled(consent);
    }

    private void deleteAllData() {
        chatMessages.clear();
        if (chatAdapter != null) chatAdapter.notifyDataSetChanged();
        if (chatHistoryManager != null) chatHistoryManager.clearChatHistory();
        usageTracker.resetDailyUsage();
        refreshQuota();
        Toast.makeText(this, "Datele au fost șterse.", Toast.LENGTH_SHORT).show();
    }

    private void showSettingsDialog() {
        String[] options = {
                "Mărește textul",
                "Micșorează textul",
                "Revocă consimțământ GDPR",
                "Șterge toate datele"
        };
        new AlertDialog.Builder(this)
                .setTitle("Setări")
                .setItems(options, (dialog, which) -> {
                    switch (which) {
                        case 0:
                            increaseTextSize();
                            break;
                        case 1:
                            decreaseTextSize();
                            break;
                        case 2:
                            privacyManager.revokeConsent(MainActivity.this);
                            break;
                        case 3:
                            deleteAllData();
                            break;
                    }
                })
                .setNegativeButton("Închide", null)
                .show();
    }

    private void refreshQuota() {
        boolean isPremium = usageTracker.isPremiumUser();
        if (isPremium) {
            quotaView.setVisibility(View.GONE);
            return;
        }
        quotaView.setVisibility(View.VISIBLE);
        quotaView.setText("Întrebări: " + (UsageTracker.FREE_TEXT_LIMIT - usageTracker.getTextCount()) + " | Poze: " + (UsageTracker.FREE_PHOTO_LIMIT + usageTracker.getRewardedTokens() - usageTracker.getPhotoCount()));
    }


    private void showDailyTip() {
        textViewDailyTip.setText(dailyTipProvider.getTodaysTip());
    }

    // ─── Feedback Dialog (AI Correction) ───────────────────────────────────────
    private void showFeedbackDialog(String originalMessage, @Nullable String base64Image) {
        AlertDialog.Builder builder = new AlertDialog.Builder(this);
        builder.setTitle("A fost corect diagnosticul?");
        builder.setPositiveButton("✅ Corect", null);
        builder.setNegativeButton("❌ Greșit", (dialog, which) -> {
            final EditText input = new EditText(this);
            input.setHint("Ce crezi că era de fapt?");

            new AlertDialog.Builder(this)
                    .setTitle("Corectare")
                    .setView(input)
                    .setPositiveButton("Trimite", (dialog2, which2) -> {
                        String corrected = input.getText().toString().trim();
                        if (!corrected.isEmpty()) {
                            JsonObject feedbackBody = new JsonObject();
                            feedbackBody.addProperty("original", originalMessage);
                            feedbackBody.addProperty("correction", corrected);
                            if (base64Image != null) {
                                feedbackBody.addProperty("image", base64Image);
                            }
                            apiService.submitFeedback(feedbackBody).enqueue(new Callback<JsonObject>() {
                                @Override
                                public void onResponse(Call<JsonObject> call, Response<JsonObject> response) {
                                    Log.d("Feedback", "Success: " + response.body());
                                }
                                @Override
                                public void onFailure(Call<JsonObject> call, Throwable t) {
                                    Log.e("Feedback", "Failed to send feedback", t);
                                }
                            });
                        }
                    })
                    .setNegativeButton("Renunță", null)
                    .show();
        });
        builder.show();
    }

    // ─── TextToSpeech Initialization ────────────────────────────────────────────
    @Override
    public void onInit(int status) {
        if (status == TextToSpeech.SUCCESS) {
            Locale romanian = new Locale("ro", "RO");
            int result = textToSpeech.setLanguage(romanian);
            if (result == TextToSpeech.LANG_MISSING_DATA ||
                    result == TextToSpeech.LANG_NOT_SUPPORTED) {
                Log.e(TAG, "TTS: Limba Română nu este suportată. Folosind Engleza.");
                textToSpeech.setLanguage(Locale.ENGLISH);
            } else {
                Log.d(TAG, "TTS inițializat în Română.");
            }
            textToSpeech.setPitch(0.9f);
            textToSpeech.setSpeechRate(0.9f);
        } else {
            Log.e(TAG, "Inițializarea TTS a eșuat: " + status);
        }
    }

    private void speakBotMessage(String message) {
        if (isTTSEnabled && textToSpeech != null && !textToSpeech.isSpeaking()) {
            textToSpeech.speak(
                    new ChatMessage(message, false, false).getCleanMessageForTTS(),
                    TextToSpeech.QUEUE_ADD,
                    null,
                    "bot_msg_" + System.currentTimeMillis()
            );
        }
    }
    private void showOnboarding() {
        new AlertDialog.Builder(this)
                .setTitle("Bun venit la GospodApp")
                .setMessage("Fotografiază plantele cu butonul cameră și pune întrebări cu microfonul sau tastatura. Pentru a reasculta acest ghid apasă Ajutor din meniu.")
                .setPositiveButton("Închide", null)
                .show();
        try {
            MediaPlayer mp = new MediaPlayer();
            AssetFileDescriptor afd = getAssets().openFd("onboarding.mp3");
            mp.setDataSource(afd.getFileDescriptor(), afd.getStartOffset(), afd.getLength());
            mp.setOnCompletionListener(MediaPlayer::release);
            mp.prepare();
            mp.start();
        } catch (Exception e) {
            // Silently ignore if audio fails
        }
    }

    private void showSocialLinksDialog() {
        String[] options = {"Facebook", "Instagram", "YouTube", "TikTok"};
        String[] urls = {
                "https://www.facebook.com/secretelegospodarului",
                "https://www.instagram.com/secretele.gospodarului/",
                "https://www.youtube.com/@secretele.gospodarului",
                "https://www.tiktok.com/@secretele.gospodarului"
        };
        new AlertDialog.Builder(this)
                .setTitle("Urmărește-ne")
                .setItems(options, (d, which) -> {
                    try {
                        startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(urls[which])));
                    } catch (Exception e) {
                        Toast.makeText(this, "Link indisponibil", Toast.LENGTH_SHORT).show();
                    }
                })
                .setNegativeButton("Închide", null)
                .show();
    }
    private void showOfflineContent() {
        if (offlineContentProvider == null) return;
        List<String> faq = offlineContentProvider.getFaq();
        StringBuilder sb = new StringBuilder();
        if (!faq.isEmpty()) {
            sb.append("Poți citi aceste sfaturi offline:\n");
            for (String f : faq) sb.append("• ").append(f).append("\n");
        }
        if (offlineManager != null) {
            List<String> cache = offlineManager.getCachedResponses();
            if (!cache.isEmpty()) {
                sb.append("\nRăspunsuri recente:\n");
                for (String r : cache) sb.append("• ").append(r).append("\n");
            }
        }
        if (sb.length() > 0) addBotMessageToChat(sb.toString());
    }


    // ─── Toggle TTS On/Off ─────────────────────────────────────────────────────
    private void toggleTTS() {
        isTTSEnabled = !isTTSEnabled;
        Toast.makeText(this, "TTS " + (isTTSEnabled ? "activat" : "dezactivat"), Toast.LENGTH_SHORT).show();
    }

    // ─── Lifecycle Methods ─────────────────────────────────────────────────────
    @Override
    protected void onResume() {
        super.onResume();
        Log.d(TAG, "onResume");
        updateGDPRUI();
        if (adView != null && usageTracker != null && !usageTracker.isPremiumUser()) {
            adView.resume();
        }
        if (usageTracker != null) usageTracker.refreshUsageData();
        refreshQuota();
        if (offlineManager != null) {
            offlineManager.retry(apiService, new Callback<JsonObject>() {
                @Override
                public void onResponse(@NonNull Call<JsonObject> call, @NonNull Response<JsonObject> response) {
                    if (response.isSuccessful() && response.body() != null) {
                        String r = safeGetResponse(response.body());
                        addBotMessageToChat(r);
                        offlineManager.cacheResponse(r);
                    }
                }

                @Override
                public void onFailure(@NonNull Call<JsonObject> call, @NonNull Throwable t) { }
            });
        }
    }

    @Override
    protected void onPause() {
        super.onPause();
        Log.d(TAG, "onPause");
        if (adView != null) adView.pause();
        if (textToSpeech != null && textToSpeech.isSpeaking()) {
            textToSpeech.stop();
        }
        if (chatHistoryManager != null && chatMessages != null) {
            chatHistoryManager.saveChatHistory(chatMessages);
        }
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        Log.d(TAG, "onDestroy");
        if (textToSpeech != null) {
            textToSpeech.stop();
            textToSpeech.shutdown();
        }
        if (adView != null) adView.destroy();
        if (loadingHandler != null) loadingHandler.removeCallbacksAndMessages(null);
        if (tflite != null) tflite.close();
        ImageProcessor.close();
    }

    // ─── Toggle the Send button on/off ─────────────────────────────────────────────
    private void updateSendButtonVisibility() {
        boolean hasText = editTextMessage.getText().toString().trim().length() > 0;
        boolean hasImage = (selectedImage != null);
        if (hasText || hasImage) {
            buttonSend.setVisibility(View.VISIBLE);
            buttonMicrophone.setVisibility(View.GONE);
        } else {
            buttonSend.setVisibility(View.GONE);
            buttonMicrophone.setVisibility(View.VISIBLE);
        }
    }

    // ─── Prompt the user when they exceed image‐API limits ─────────────────────────
    private void showUpgradeDialog(String msg) {
        new AlertDialog.Builder(this)
                .setTitle("Upgrade Premium")
                .setMessage(msg)
                .setPositiveButton("Abonare", (d, w) -> showPremiumUpgradeDialog())
                .setNegativeButton("Mai târziu", null)
                .show();
    }

    // ─── Prompt the user when they exceed text‐API limits ──────────────────────────
    private void showRewardedAdOrUpgrade() {
        boolean shown = AdHelper.show(this, () -> {
            usageTracker.addRewardedToken();
            refreshQuota();
        });
        if (!shown) {
            showUpgradeDialog("Vizualizează un video de 15 sec pentru încă o analiză foto gratuită.");
        }
    }

    // ─── Show Premium‐upgrade dialog ───────────────────────────────────────────────
    private void showPremiumUpgradeDialog() {
        new AlertDialog.Builder(this)
                .setTitle("🌟 GospodApp Premium")
                .setMessage(
                        "✅ Întrebări text NELIMITATE\n" +
                                "✅ 5 analize foto AVANSATE/zi\n" +
                                "✅ Fără reclame\n\n" +
                                "Doar 4.99 RON / lună"
                )
                .setPositiveButton("Activează Premium", (d, w) -> {
                    // TODO: trigger your in‐app purchase flow here
                    Toast.makeText(this, "Achiziție Premium (dev)", Toast.LENGTH_LONG).show();
                })
                .setNegativeButton("Poate altă dată", null)
                .show();
    }

    // ─── Weather Helper ─────────────────────────────────────────────────────
    private String fetchWeather() {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION)
                != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(this,
                    new String[]{Manifest.permission.ACCESS_COARSE_LOCATION},
                    REQUEST_LOCATION_PERMISSION);
            return null;
        }
        try {
            android.location.LocationManager lm = (android.location.LocationManager) getSystemService(LOCATION_SERVICE);
            android.location.Location loc = lm.getLastKnownLocation(android.location.LocationManager.NETWORK_PROVIDER);
            if (loc == null) return null;
            String url = "https://api.openweathermap.org/data/2.5/weather?lat=" + loc.getLatitude()
                    + "&lon=" + loc.getLongitude() + "&units=metric&lang=ro&appid=" + ApiClient.OPEN_WEATHER_KEY;
            java.net.HttpURLConnection conn = (java.net.HttpURLConnection) new java.net.URL(url).openConnection();
            conn.setConnectTimeout(5000);
            conn.setReadTimeout(5000);
            java.io.InputStream is = conn.getInputStream();
            java.io.BufferedReader br = new java.io.BufferedReader(new java.io.InputStreamReader(is));
            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = br.readLine()) != null) sb.append(line);
            br.close();
            org.json.JSONObject json = new org.json.JSONObject(sb.toString());
            String desc = json.getJSONArray("weather").getJSONObject(0).getString("description");
            float temp = (float) json.getJSONObject("main").getDouble("temp");
            int hum = json.getJSONObject("main").getInt("humidity");
            return String.format(Locale.US, "Vreme: %.0f°C, %s, umiditate %d%%", temp, desc, hum);
        } catch (Exception e) {
            Log.e(TAG, "Weather error: " + e.getMessage());
            return null;
        }
    }


    // ─── Notification Helpers ────────────────────────────────────────────────
    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                    CHANNEL_ID,
                    "Sfaturi zilnice",
                    NotificationManager.IMPORTANCE_DEFAULT
            );
            channel.setDescription("Notificări pentru sfatul zilnic de grădinărit");
            NotificationManager manager = getSystemService(NotificationManager.class);
            if (manager != null) manager.createNotificationChannel(channel);
        }
    }

    private PendingIntent getNotificationPendingIntent() {
        Intent intent = new Intent(this, NotificationReceiver.class);
        return PendingIntent.getBroadcast(
                this,
                NOTIF_REQUEST_CODE,
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );
    }

    private void scheduleDailyTipNotification() {
        AlarmManager alarm = (AlarmManager) getSystemService(ALARM_SERVICE);
        if (alarm == null) return;
        long now = System.currentTimeMillis();
        java.util.Calendar c = java.util.Calendar.getInstance();
        c.set(java.util.Calendar.HOUR_OF_DAY, 9);
        c.set(java.util.Calendar.MINUTE, 0);
        c.set(java.util.Calendar.SECOND, 0);
        c.set(java.util.Calendar.MILLISECOND, 0);
        long trigger = c.getTimeInMillis();
        if (trigger <= now) {
            c.add(java.util.Calendar.DAY_OF_YEAR, 1);
            trigger = c.getTimeInMillis();
        }
        alarm.setInexactRepeating(AlarmManager.RTC_WAKEUP, trigger,
                AlarmManager.INTERVAL_DAY, getNotificationPendingIntent());
    }

    private void cancelDailyTipNotification() {
        AlarmManager alarm = (AlarmManager) getSystemService(ALARM_SERVICE);
        if (alarm != null) {
            alarm.cancel(getNotificationPendingIntent());
        }
    }

    private boolean isNotificationsEnabled() {
        SharedPreferences prefs = getSharedPreferences(PREFS, MODE_PRIVATE);
        return prefs.getBoolean(KEY_NOTIF_ENABLED, true);
    }

    private void setNotificationsEnabled(boolean enabled) {
        SharedPreferences prefs = getSharedPreferences(PREFS, MODE_PRIVATE);
        prefs.edit().putBoolean(KEY_NOTIF_ENABLED, enabled).apply();
        if (enabled) {
            scheduleDailyTipNotification();
        } else {
            cancelDailyTipNotification();
        }
    }

    private void showNotificationSettings() {
        android.widget.Switch switchView = new android.widget.Switch(this);
        switchView.setChecked(isNotificationsEnabled());
        switchView.setText("Notificări zilnice");
        int pad = (int) (16 * getResources().getDisplayMetrics().density);
        switchView.setPadding(pad, pad, pad, pad);
        switchView.setTextSize(android.util.TypedValue.COMPLEX_UNIT_SP, 18);
        new AlertDialog.Builder(this)
                .setTitle("Notificări")
                .setView(switchView)
                .setPositiveButton("OK", (d, w) -> setNotificationsEnabled(switchView.isChecked()))
                .setNegativeButton("Anulează", null)
                .show();
    }

    public static class NotificationReceiver extends BroadcastReceiver {
        @Override
        public void onReceive(Context context, Intent intent) {
            Intent openIntent = new Intent(context, MainActivity.class);
            openIntent.putExtra("show_tip", true);
            PendingIntent contentIntent = PendingIntent.getActivity(
                    context,
                    0,
                    openIntent,
                    PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
            );

            DailyTipProvider provider = new DailyTipProvider(context);
            String tip = provider.getTodaysTip();
            if (tip.length() > 120) tip = tip.substring(0, 120) + "...";


            NotificationCompat.Builder builder = new NotificationCompat.Builder(context, CHANNEL_ID)
                    .setSmallIcon(android.R.drawable.ic_dialog_info)
                    .setContentTitle("\uD83C\uDF31 Sfatul zilnic")
                    .setContentText(tip)
                    .setContentIntent(contentIntent)
                    .setAutoCancel(true)
                    .setPriority(NotificationCompat.PRIORITY_HIGH);

            NotificationManagerCompat.from(context).notify(NOTIF_ID, builder.build());
        }
    }

}


