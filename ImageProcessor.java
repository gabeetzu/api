package com.secretele.gospodarului;

import android.content.Context;
import android.content.res.AssetFileDescriptor;
import android.graphics.Bitmap;
import android.util.Log;
import android.util.Pair;

import org.tensorflow.lite.Interpreter;

import java.io.FileInputStream;
import java.io.IOException;
import java.nio.ByteBuffer;
import java.nio.ByteOrder;
import java.nio.channels.FileChannel;
import java.util.Locale;

public class ImageProcessor {
    private static final String TAG = "ImageProcessor";
    private static final String MODEL_FILE = "plant_model_5Classes.tflite";
    private static final int INPUT_SIZE = 224;
    private static final String[] LABELS = MainActivity.CNN_LABELS;

    private static Interpreter interpreter;

    private static synchronized Interpreter getInterpreter(Context context) throws IOException {
        if (interpreter == null) {
            AssetFileDescriptor fd = context.getAssets().openFd(MODEL_FILE);
            FileInputStream inputStream = new FileInputStream(fd.getFileDescriptor());
            FileChannel channel = inputStream.getChannel();
            ByteBuffer buffer = channel.map(FileChannel.MapMode.READ_ONLY, fd.getStartOffset(), fd.getDeclaredLength());
            interpreter = new Interpreter(buffer);
        }
        return interpreter;
    }

    private static ByteBuffer preprocess(Bitmap bitmap) {
        Bitmap resized = Bitmap.createScaledBitmap(bitmap, INPUT_SIZE, INPUT_SIZE, true);
        ByteBuffer input = ByteBuffer.allocateDirect(INPUT_SIZE * INPUT_SIZE * 3 * 4);
        input.order(ByteOrder.nativeOrder());
        int[] pixels = new int[INPUT_SIZE * INPUT_SIZE];
        resized.getPixels(pixels, 0, resized.getWidth(), 0, 0, resized.getWidth(), resized.getHeight());
        for (int pixel : pixels) {
            input.putFloat(((pixel >> 16) & 0xFF) / 255f);
            input.putFloat(((pixel >> 8) & 0xFF) / 255f);
            input.putFloat((pixel & 0xFF) / 255f);
        }
        resized.recycle();
        input.rewind();
        return input;
    }

    public static Pair<String, Float> getDiagnosisAndConfidence(Context context, Bitmap bitmap) {
        if (bitmap == null) return new Pair<>(null, 0f);
        try {
            Interpreter tflite = getInterpreter(context);
            ByteBuffer input = preprocess(bitmap);
            float[][] output = new float[1][LABELS.length];
            tflite.run(input, output);
            float max = -1f;
            int idx = -1;
            for (int i = 0; i < LABELS.length; i++) {
                if (output[0][i] > max) {
                    max = output[0][i];
                    idx = i;
                }
            }
            if (idx >= 0 && max >= 0.75f) {
                return new Pair<>(LABELS[idx], max);
            }
        } catch (Exception e) {
            Log.e(TAG, "Inference error", e);
        }
        return new Pair<>(null, 0f);
    }

    public static void close() {
        if (interpreter != null) {
            interpreter.close();
            interpreter = null;
        }
    }
}