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
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.appcompat.app.AppCompatActivity;
import androidx.cardview.widget.CardView;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.google.android.gms.ads.AdRequest;
import com.google.android.gms.ads.AdView;
import com.google.android.gms.ads.LoadAdError;
import com.google.android.gms.ads.MobileAds;
import com.google.android.gms.ads.rewarded.RewardedAd;
import com.google.android.gms.ads.rewarded.RewardedAdLoadCallback;
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

public class MainActivity extends AppCompatActivity implements TextToSpeech.OnInitListener {

    // ─── Constants ─────────────────────────────────────────────────────────────
    private static final String TAG = "MainActivity";
    private static final int REQUEST_IMAGE_CAPTURE = 1;
    private static final int REQUEST_IMAGE_GALLERY = 2;
    private static final int REQUEST_SPEECH_INPUT = 3;
    private static final int REQUEST_CAMERA_PERMISSION = 100;
    private static final int REQUEST_STORAGE_PERMISSION = 101;
    private static final int REQUEST_MIC_PERMISSION = 102;
    private static final int REQUEST_LOCATION_PERMISSION = 103;

    private static final String PREFS = "gospod_prefs";
    private static final String KEY_NOTIF_ENABLED = "daily_tip_enabled";
    private static final String CHANNEL_ID = "daily_tip_channel";
    private static final int NOTIF_ID = 1001;
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
    private ImageButton buttonSend, buttonCamera, buttonMicrophone, buttonGallery, buttonMenu, buttonRemoveImage;
    private ImageView imagePreview, dailyTipArrow;
    private TextView textViewDailyTip;
    private LinearLayout dailyTipHeader;
    private CardView imagePreviewContainer;
    private AdView adView;

    // ─── Core Components ───────────────────────────────────────────────────────
    private ChatAdapter chatAdapter;
    private List<ChatMessage> chatMessages;
    private ApiService apiService;
    private TextToSpeech textToSpeech;
    private UsageTracker usageTracker;
    private ChatHistoryManager chatHistoryManager;
    private AbuseFilter abuseFilter;
    private DailyTipProvider dailyTipProvider;
    private PrivacyManager privacyManager;
    private Interpreter tflite;
    private RewardedAd rewardedAd;

    // ─── State ─────────────────────────────────────────────────────────────────
    private Bitmap selectedImage;
    private boolean isTTSEnabled = false;
    private boolean isProcessingRequest = false;
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
        if (privacyManager.shouldShowDisclaimer()) {
            privacyManager.showPrivacyDialog(this);
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
        initializeAds();
        loadInitialData();


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

// ← DO NOT put a “}” here! (That was the stray brace you must remove.)

    // ─── initializeCoreComponents() ─────────────────────────────────────────────
    private void initializeCoreComponents() {
        Log.d(TAG, "Initializing core components...");
        apiService = ApiClient.getApiService();
        usageTracker = new UsageTracker(this);
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
        buttonGallery = findViewById(R.id.buttonGallery);
        buttonMicrophone = findViewById(R.id.imageButtonMic);
        buttonMenu = findViewById(R.id.buttonMenu);
        buttonRemoveImage = findViewById(R.id.buttonRemoveImage);
        imagePreview = findViewById(R.id.imagePreview);
        imagePreviewContainer = findViewById(R.id.imagePreviewContainer);
        textViewDailyTip = findViewById(R.id.textViewDailyTip);
        dailyTipHeader = findViewById(R.id.dailyTipHeader);
        dailyTipArrow = findViewById(R.id.dailyTipArrow);
        adView = findViewById(R.id.adView);

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
        buttonGallery.setOnClickListener(v -> checkStoragePermissionAndOpenGallery());
        buttonMicrophone.setOnClickListener(v -> startVoiceInput());
        buttonMenu.setOnClickListener(this::showPopupMenu);
        buttonRemoveImage.setOnClickListener(v -> clearSelectedImage());
        dailyTipHeader.setOnClickListener(v -> toggleDailyTip());

        updateSendButtonVisibility();
        Log.d(TAG, "UI and Listeners Initialized.");
    }

    // ─── initializeAds() ───────────────────────────────────────────────────────
    private void initializeAds() {
        MobileAds.initialize(this, initializationStatus -> { });
        AdRequest adRequest = new AdRequest.Builder().build();
        if (adView != null) adView.loadAd(adRequest);
        loadRewardedAd();
    }

    // ─── loadRewardedAd() ────────────────────────────────────────────────────────
    private void loadRewardedAd() {
        RewardedAd.load(
                this,
                getString(R.string.admob_rewarded_id),
                new AdRequest.Builder().build(),
                new RewardedAdLoadCallback() {
                    @Override
                    public void onAdLoaded(@NonNull RewardedAd ad) {
                        rewardedAd = ad;
                    }
                    @Override
                    public void onAdFailedToLoad(@NonNull LoadAdError adError) {
                        rewardedAd = null;
                    }
                }
        );
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
                                        usageTracker.recordTextAPICall();
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

        addUserMessageToChat(userInput, selectedImage);
        editTextMessage.setText("");

        // Usage limits
        if (selectedImage != null && !usageTracker.canMakeImageAPICall()) {
            showUpgradeDialogForImages();
            clearSelectedImage();
            return;
        }
        if (selectedImage == null && !usageTracker.canMakeTextAPICall()) {
            showUpgradeDialogForText();
            return;
        }

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

    private void checkStoragePermissionAndOpenGallery() {
        String perm = (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU)
                ? Manifest.permission.READ_MEDIA_IMAGES
                : Manifest.permission.READ_EXTERNAL_STORAGE;
        if (ContextCompat.checkSelfPermission(this, perm)
                != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(
                    this, new String[]{perm}, REQUEST_STORAGE_PERMISSION
            );
        } else {
            openGallery();
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

    private void openGallery() {
        Intent intent = new Intent(Intent.ACTION_PICK, MediaStore.Images.Media.EXTERNAL_CONTENT_URI);
        try {
            startActivityForResult(intent, REQUEST_IMAGE_GALLERY);
        } catch (Exception e) {
            Toast.makeText(this, "Eroare la deschiderea galeriei.", Toast.LENGTH_SHORT).show();
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
            else if (reqCode == REQUEST_STORAGE_PERMISSION) openGallery();
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
            } else if (reqCode == REQUEST_IMAGE_GALLERY) {
                Uri uri = data.getData();
                if (uri != null) {
                    try {
                        selectedImage = MediaStore.Images.Media.getBitmap(getContentResolver(), uri);
                        showImagePreviewUI(selectedImage);
                    } catch (IOException e) {
                        Log.e(TAG, "Gallery error: " + e.getMessage());
                    }
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
            } else if (id == R.id.action_premium) {
                showPremiumUpgradeDialog();
                return true;
            } else if (id == R.id.action_privacy_policy) {
                openLegalDocument("https://gospodapp.ro/politica-confidentialitate");
                return true;
            } else if (id == R.id.action_terms) {
                openLegalDocument("https://gospodapp.ro/termeni-si-conditii");
                return true;
            } else if (id == R.id.action_notifications) {
                showNotificationSettings();
                return true;
            } else if (id == R.id.action_revoke_consent) {
                privacyManager.revokeConsent(MainActivity.this);
                return true;
            } else if (id == R.id.action_delete_data) {
                privacyManager.deleteAllUserData();
                Toast.makeText(this, "Toate datele au fost șterse", Toast.LENGTH_LONG).show();
                recreate(); // Consider if this is the best user experience or if there's a less disruptive way
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
        if (adView != null && usageTracker != null && !usageTracker.isPremiumUser()) {
            adView.resume();
        }
        if (usageTracker != null) usageTracker.refreshUsageData();
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
        buttonSend.setVisibility((hasText || hasImage) ? View.VISIBLE : View.GONE);
    }

    // ─── Prompt the user when they exceed image‐API limits ─────────────────────────
    private void showUpgradeDialogForImages() {
        new AlertDialog.Builder(this)
                .setTitle("Limită Imagini Atinsă")
                .setMessage("Ai folosit analiza gratuită. Premium: 5 analize/zi, text nelimitat, fără reclame.")
                .setPositiveButton("Upgrade Premium", (d, w) -> showPremiumUpgradeDialog())
                .setNegativeButton("Urmărește Reclamă", (d, w) -> {
                    if (rewardedAd != null) {
                        rewardedAd.show(MainActivity.this, rewardItem -> {
                            usageTracker.addExtraImageCredits(1);
                            Toast.makeText(this, "Ai primit 1 analiză foto extra!", Toast.LENGTH_SHORT).show();
                            loadRewardedAd();
                        });
                    } else {
                        Toast.makeText(this, "Reclama nu este încărcată.", Toast.LENGTH_SHORT).show();
                        loadRewardedAd();
                    }
                })
                .setNeutralButton("Anulează", null)
                .show();
    }

    // ─── Prompt the user when they exceed text‐API limits ──────────────────────────
    private void showUpgradeDialogForText() {
        new AlertDialog.Builder(this)
                .setTitle("Limită Întrebări Atinsă")
                .setMessage("Ai folosit întrebările gratuite. Premium: text nelimitat, 5 analize/zi, fără reclame.")
                .setPositiveButton("Upgrade Premium", (d, w) -> showPremiumUpgradeDialog())
                .setNegativeButton("Urmărește Reclamă", (d, w) -> {
                    if (rewardedAd != null) {
                        rewardedAd.show(MainActivity.this, rewardItem -> {
                            usageTracker.addExtraQuestions(3);
                            Toast.makeText(this, "Ai primit 3 întrebări text extra!", Toast.LENGTH_SHORT).show();
                            loadRewardedAd();
                        });
                    } else {
                        Toast.makeText(this, "Reclama nu este încărcată.", Toast.LENGTH_SHORT).show();
                        loadRewardedAd();
                    }
                })
                .setNeutralButton("Anulează", null)
                .show();
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


