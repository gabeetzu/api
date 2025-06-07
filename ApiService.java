package com.secretele.gospodarului;

import com.google.gson.JsonObject;
import retrofit2.Call;
import retrofit2.http.Body;
import retrofit2.http.POST;

public interface ApiService {

    @POST("process-image.php")
    Call<JsonObject> processRequest(@Body JsonObject body);
    @POST("submit_feedback.php")  // ✅ NEW
    Call<JsonObject> submitFeedback(@Body JsonObject body);
}
