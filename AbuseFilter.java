package com.secretele.gospodarului;

import android.content.Context;
import android.content.res.AssetManager;
import java.io.BufferedReader;
import java.io.IOException;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class AbuseFilter {
    
    private Context context;
    private List<String> badWords;
    private List<String> offTopicKeywords;
    private List<String> positiveWords;
    
    public AbuseFilter(Context context) {
        this.context = context;
        this.badWords = new ArrayList<>();
        this.offTopicKeywords = new ArrayList<>();
        this.positiveWords = new ArrayList<>();
        loadFilterData();
    }
    
    private void loadFilterData() {
        loadBadWords();
        loadOffTopicKeywords();
        loadPositiveWords();
    }
    
    private void loadBadWords() {
        try {
            AssetManager assetManager = context.getAssets();
            InputStream inputStream = assetManager.open("bad_words.txt");
            BufferedReader reader = new BufferedReader(new InputStreamReader(inputStream, "UTF-8"));
            
            String line;
            while ((line = reader.readLine()) != null) {
                if (!line.trim().isEmpty()) {
                    badWords.add(line.trim().toLowerCase());
                }
            }
            
            reader.close();
            inputStream.close();
            
        } catch (IOException e) {
            e.printStackTrace();
            // Add default bad words if file doesn't exist
            addDefaultBadWords();
        }
    }
    
    private void addDefaultBadWords() {
        // Add common inappropriate words in Romanian
        badWords.addAll(Arrays.asList(
            "prostie", "idiot", "prost", "tâmpit", "imbecil",
            "dracu", "naiba", "dracului", "fmm"
        ));
    }
    
    private void loadOffTopicKeywords() {
        // Keywords that indicate off-topic content
        offTopicKeywords.addAll(Arrays.asList(
            "politică", "politician", "alegeri", "vot", "parlament",
            "fotbal", "meci", "gol", "echipa", "sport",
            "sex", "amor", "dragoste", "relație", "căsătorie",
            "bani", "credit", "bancă", "investiție", "crypto",
            "mașină", "automobil", "șofer", "benzină",
            "telefon", "computer", "laptop", "tehnologie",
            "film", "cinema", "actor", "actriță", "muzică",
            "restaurant", "mâncare", "rețetă", "gătit",
            "vacanță", "călătorie", "hotel", "avion",
            "îmbrăcăminte", "pantofi", "haine", "modă"
        ));
    }
    
    private void loadPositiveWords() {
        // Words that indicate positive/bragging comments about plants
        positiveWords.addAll(Arrays.asList(
            "frumoasă", "frumos", "superb", "superbă", "minunat", "minunată",
            "sănătoasă", "sănătos", "mândru", "mândră", "perfect", "perfectă",
            "excelent", "excelentă", "uimitor", "uimitoare", "fantastic", "fantastică",
            "incredibil", "incredibilă", "magnific", "magnifică", "splendid", "splendidă",
            "grozav", "grozavă", "exceptional", "excepțională"
        ));
    }
    
    public boolean isAbusive(String text) {
        if (text == null || text.trim().isEmpty()) {
            return false;
        }
        
        String normalizedText = text.toLowerCase();
        
        for (String badWord : badWords) {
            if (normalizedText.contains(badWord)) {
                return true;
            }
        }
        
        return false;
    }
    
    public boolean isOffTopic(String text) {
        if (text == null || text.trim().isEmpty()) {
            return false;
        }
        
        String normalizedText = text.toLowerCase();
        
        // Check if text contains gardening-related keywords
        if (containsGardeningKeywords(normalizedText)) {
            return false; // It's about gardening, so it's on-topic
        }
        
        // Check if it contains off-topic keywords
        for (String keyword : offTopicKeywords) {
            if (normalizedText.contains(keyword)) {
                return true;
            }
        }
        
        // If it's a very short message without gardening context, consider it potentially off-topic
        if (normalizedText.length() < 10 && !containsGardeningKeywords(normalizedText)) {
            return true;
        }
        
        return false;
    }
    
    private boolean containsGardeningKeywords(String text) {
        String[] gardeningKeywords = {
            "plantă", "plante", "grădină", "gradina", "răsad", "rasad",
            "semințe", "seminte", "pământ", "pamant", "sol", "compost",
            "udare", "apa", "îngrășământ", "ingrasamant", "floare", "flori",
            "legume", "fructe", "pomi", "copac", "copaci", "frunze",
            "rădăcini", "radacini", "tulpină", "tulpina", "muguri",
            "înflorire", "inflorire", "recoltare", "recolta", "cultură",
            "cultura", "seră", "sera", "răsadniță", "rasadnita",
            "roșii", "rosii", "tomate", "ardei", "castraveți", "castraveti",
            "ceapă", "ceapa", "usturoi", "morcovi", "cartof", "cartofi",
            "salată", "salata", "spanac", "mărar", "marar", "pătrunjel",
            "patrunjel", "busuioc", "mentă", "menta", "trandafir", "trandafiri",
            "lalele", "narcise", "crini", "bujor", "bujori",
            "dăunători", "daunatori", "insecte", "păduchi", "paduchi",
            "afide", "gândaci", "gandaci", "omizi", "melci", "limacși",
            "boli", "ciupercă", "ciuperci", "mucegai", "rugină", "rugina",
            "ofilire", "galbenire", "uscăciune", "uscaciune",
            "tăiere", "taiere", "prăjire", "prajire", "mulcire", "săpare",
            "sapare", "plantare", "semănare", "semanare", "transplantare"
        };
        
        for (String keyword : gardeningKeywords) {
            if (text.contains(keyword)) {
                return true;
            }
        }
        
        return false;
    }
    
    public boolean isPositiveComment(String text) {
        if (text == null || text.trim().isEmpty()) {
            return false;
        }
        
        String normalizedText = text.toLowerCase();
        
        for (String positiveWord : positiveWords) {
            if (normalizedText.contains(positiveWord)) {
                return true;
            }
        }
        
        return false;
    }
    
    public void addBadWord(String word) {
        if (word != null && !word.trim().isEmpty()) {
            badWords.add(word.trim().toLowerCase());
        }
    }
    
    public void addOffTopicKeyword(String keyword) {
        if (keyword != null && !keyword.trim().isEmpty()) {
            offTopicKeywords.add(keyword.trim().toLowerCase());
        }
    }
    
    public void addPositiveWord(String word) {
        if (word != null && !word.trim().isEmpty()) {
            positiveWords.add(word.trim().toLowerCase());
        }
    }
}