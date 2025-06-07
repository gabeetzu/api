package com.secretele.gospodarului;

import android.content.Context;
import android.content.res.AssetManager;
import com.google.gson.Gson;
import com.google.gson.reflect.TypeToken;
import java.io.IOException;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.lang.reflect.Type;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.HashMap;
import java.util.Locale;
import java.util.Map;

public class DailyTipProvider {
    
    private Context context;
    private Map<String, String> dailyTips;
    private Gson gson;
    
    public DailyTipProvider(Context context) {
        this.context = context;
        this.gson = new Gson();
        this.dailyTips = new HashMap<>();
        loadDailyTips();
    }
    
    private void loadDailyTips() {
        try {
            AssetManager assetManager = context.getAssets();
            InputStream inputStream = assetManager.open("daily_tips.json");
            InputStreamReader reader = new InputStreamReader(inputStream, "UTF-8");
            
            Type mapType = new TypeToken<Map<String, String>>(){}.getType();
            dailyTips = gson.fromJson(reader, mapType);
            
            if (dailyTips == null) {
                dailyTips = new HashMap<>();
                addDefaultTips();
            }
            
            reader.close();
            inputStream.close();
            
        } catch (IOException e) {
            e.printStackTrace();
            dailyTips = new HashMap<>();
            addDefaultTips();
        }
    }
    
    private void addDefaultTips() {
        // January tips
    dailyTips.put("01-01", "Planifică rotația culturilor pentru noul an. Schimbă locul plantelor din aceeași familie (solanacee, cucurbitacee) pentru a preveni epuizarea solului.");
    dailyTips.put("01-02", "Pregătește compostul pentru primăvară: amestecă 3 straturi de resturi vegetale cu 1 strat de gunoi de grajd vechi de 6 luni.");
    dailyTips.put("01-03", "Verifică sămânța de roșii veche: pune 10 semințe pe șervețel umed. Dacă mai puțin de 7 germinează, cumpară altele.");
    dailyTips.put("01-04", "Tăiați vița de vie până la 15 ianuarie. Folosește foarfecă deosebită și dezinfectează cu alcool după fiecare tăietură.");
    dailyTips.put("01-05", "Pregătește un amestec anti-păduchi: 1 litru apă + 2 linguri ulei de neem + 1 lingură săpun lichid. Stropiți pomii fructiferi.");
    dailyTips.put("01-06", "Înfășoară trunchiurile pomilor tineri cu pânză de sac pentru a preveni crapăturile de la ger.");
    dailyTips.put("01-07", "Plantează usturoi în sere: distanță 15 cm între căței, adâncime 5 cm. Folosește compost ca strat de bază.");
    dailyTips.put("01-08", "Fă un plan de irigații: calculează necesarul de apă pe bază de suprafață cultivată și tipul solului.");
    dailyTips.put("01-09", "Curăță uneltele de tăiat cu oțet și piatră ponce. Unelte ascuțite previn răni infectate la plante.");
    dailyTips.put("01-10", "Semănază rucola în mini-sere interioare: temperatura optimă 15-18°C, lumina indirectă 6 ore/zi.");
    dailyTips.put("01-11", "Prepară alifie pentru răni la pomi: 1 parte ceară de albine + 2 părți ulei de măsline. Topeste la baie marie.");
    dailyTips.put("01-12", "Testează pH-ul solului cu var/orez: pune pământ în borcan cu apă și o lingură de orez. Dacă spumește, solul e acid.");
    dailyTips.put("01-13", "Plantează arpagic în ghivece pe pervaz. Folosește sol cu 30% nisip pentru drenaj.");
    dailyTips.put("01-14", "Construiește un cadru rece din rame vechi de geamuri pentru culturi timpurii de primăvară.");
    dailyTips.put("01-15", "Îndepărtează lichenii de pe pomii bătrâni cu perie de sârmă. Nu folosi cuțite pentru a nu răni scoarța.");
    dailyTips.put("01-16", "Semănază levănțica în căsuțe de ouă: pune 3 semințe/căsuță, acoperă cu folie până la germinare.");
    dailyTips.put("01-17", "Pregătește tăvițe pentru răsaduri: spală cu săpun de vase și clătește cu apă cu puțină bleană.");
    dailyTips.put("01-18", "Fă un spray anti-fungi din 1 litru apă + 3 căței de usturoi zdrobiți. Lasă 24h, strecoară și stropeste răsadurile.");
    dailyTips.put("01-19", "Înmulțește afine prin butași: taie lăstari de 15 cm, plantează în amestec turbă + nisip (2:1).");
    dailyTips.put("01-20", "Verifică bulbii de lalele depozitați: aruncă cei mucegăiți sau moi. Păstrează doar cei tari și uscați.");
    dailyTips.put("01-21", "Semănază praz pentru răsad: folosește sol profund de 20 cm. Plantează semințe la 1 cm adâncime.");
    dailyTips.put("01-22", "Construiește un sistem de captare a apei de ploaie din jgheaburi vechi. Folosește doze de 200L.");
    dailyTips.put("01-23", "Pregătește un amestec de sol pentru răsaduri: 1/3 compost + 1/3 nisip + 1/3 turbă. Sterilizează la 120°C 30 min.");
    dailyTips.put("01-24", "Plantează mentă în recipiente izolate (e invazivă). Folosește ghivece de plastic îngropate în sol.");
    dailyTips.put("01-25", "Semănază salvie în căsuțe: acoperă semințele cu 0,5 cm sol. Germinare la 21°C în 14-21 zile.");
    dailyTips.put("01-26", "Fă un îngrășământ lichid din compost: pune 1 kg compost în 5L apă, lasă 3 zile, amestecă zilnic. Filtratul se diluează 1:10.");
    dailyTips.put("01-27", "Verifică sămânța de ardei iute: pune semințe între șervețele umede. Dacă nu germinează în 10 zile, înlocuiește.");
    dailyTips.put("01-28", "Pregătește etichete pentru răsaduri din lemn de la cutii de fructe. Scrie cu marker permanent rezistent la apă.");
    dailyTips.put("01-29", "Plantează rozmarin prin butași: taie lăstari de 10 cm, îndepărtează frunzele de jos, plantează în nisip umed.");
    dailyTips.put("01-30", "Semănază telina în săculețe de celuloză. Folosește sol bogat în humus și păstrează umed.");
    dailyTips.put("01-31", "Pregătește un calendar lunar de grădinărit: semănat la lună crescătoare, tăieri la lună descrescătoare.");

// FEBRUARY - 28 days (29 in leap years)
dailyTips.put("02-01", "Taie pomii fructiferi tineri în formă de vas: păstrează 3-5 ramuri principale la 45° față de trunchi pentru circulația optimă a aerului [1]. Dezinfectează foarfeca cu alcool după fiecare tăietură pentru a preveni răspândirea bolilor [2].");

dailyTips.put("02-02", "Pregătește substrat pentru răsaduri: amestecă 40% turbă, 30% compost matur, 20% perlită și 10% nisip de râu [1]. Sterilizează amestecul la 80°C timp de 30 de minute în cuptor [2].");

dailyTips.put("02-03", "Semănă basilic în căsuțe pe pervaz: temperatura optimă de germinare este 20-25°C [1]. Acoperă semințele cu 0,5cm de substrat și menține umiditatea constantă cu folie transparentă [2].");

dailyTips.put("02-04", "Tratează pomii cu ulei de dormant: amestecă 200ml ulei horticol la 10L apă și stropește când temperatura e peste 5°C [1]. Acest tratament elimină ouăle de păduchi și acarieni [2].");

dailyTips.put("02-05", "Plantează cartofii pentru germinare într-un loc luminos și răcoros (12-15°C) [1]. Așează tuberculi cu ochii în sus pentru a stimula dezvoltarea lăstarilor viguroși [2].");

dailyTips.put("02-06", "Pregătește soluție cu ceapă pentru tratarea semințelor: fierbe 1kg ceapă în 2L apă, strecoară și lasă semințele 2 ore în soluție [1]. Această metodă naturală previne putregaiul semințelor [2].");

dailyTips.put("02-07", "Verifică bulbii depozitați și îndepărtează cei cu semne de mucegai sau înmuiere [1]. Păstrează doar bulbii fermi și fără pete pentru plantarea de primăvară [2].");

dailyTips.put("02-08", "Semănă ridichile în sera încălzită: folosește soiuri timpurii rezistente la frig [1]. Distanța între semințe să fie de 3-4cm pentru dezvoltarea optimă a rădăcinii [2].");

dailyTips.put("02-09", "Pregătește cadre reci pentru grădinitul timpuriu: orientează-le spre sud și asigură ventilație reglabilă [1]. Temperatura interioară să nu depășească 25°C ziua [2].");

dailyTips.put("02-10", "Taie și mulcește trandafirii: îndepărtează crengile moarte și bolnave la 1cm deasupra unui ochi sănătos [1]. Aplică compost în jurul bazei și acoperă cu paie [2].");

dailyTips.put("02-11", "Semănă praz pentru răsaduri în tăvițe adânci de minimum 15cm [1]. Substrat ideal: 50% turbă, 30% compost, 20% nisip pentru drenaj excelent [2].");

dailyTips.put("02-12", "Pregătește îngrășământ din cenușă: amestecă 1kg cenușă de lemn dur cu 5kg compost [1]. Acest amestec furnizează potasiu pentru fructificarea abundentă [2].");

dailyTips.put("02-13", "Plantează usturoiul în ghivece pentru forțat: folosește căței mari și sănătoși [1]. Temperatura optimă de germinare este 4-10°C timp de 6 săptămâni [2].");

dailyTips.put("02-14", "Semănă spanacul gigant de iarnă în sera neîncălzită [1]. Aceste soiuri rezistă la -10°C și oferă frunze crocante până în aprilie [2].");

dailyTips.put("02-15", "Pregătește soluție nutritivă pentru răsaduri: diluează îngrășământul lichid complex la 25% din concentrația recomandată [1]. Aplică o dată pe săptămână pentru creștere uniformă [2].");

dailyTips.put("02-16", "Verifică sistemul de drenaj al ghivecelor: asigură-te că orificiile nu sunt înfundate [1]. Drainage-ul deficient provoacă putregaiul rădăcinilor și moartea plantelor [2].");

dailyTips.put("02-17", "Semănă telina pentru răsaduri în substrat fin tamisat [1]. Semințele sunt foarte mici și necesită lumină pentru germinare - nu le acoperi complet [2].");

dailyTips.put("02-18", "Tratează uneltele de tăiat cu pasta abrazivă pentru ascuțire [1]. Unelte ascuțite fac tăieturi curate care se vindecă rapid și reduc riscul de infecții [2].");

dailyTips.put("02-19", "Pregătește bancile de germinare cu rezistență electrică pentru menținerea temperaturii de 20-25°C [1]. Controlul precis al temperaturii măre rata de germinare cu 40% [2].");

dailyTips.put("02-20", "Semănă salata în sere pentru prima recoltă de primăvară [1]. Alege soiuri rezistente la montare timpurie pentru rezultate optime [2].");

dailyTips.put("02-21", "Aplică tratament foliar cu extract de alge marine pentru întărirea plantelor [1]. Microelementele din alge îmbunătățesc rezistența la stres și boli [2].");

dailyTips.put("02-22", "Pregătește soluția pentru dezinfectarea semințelor cu permanganat de potasiu 0,1% [1]. Tratamentul de 20 minute elimină patogenii de pe suprafața semințelor [2].");

dailyTips.put("02-23", "Verifică și ajustează pH-ul substratului pentru răsaduri la 6.0-6.8 [1]. Folosește turnesol sau pH-metru digital pentru măsurători precise [2].");

dailyTips.put("02-24", "Semănă mărul pământului (topinambur) în ghivece pentru înmulțire [1]. Taie tuberculii în bucăți cu câte 2-3 ochi și lasă să se usuce 24 ore [2].");

dailyTips.put("02-25", "Pregătește amestec de țărână pentru plantele acidofile: 40% turbă de sfagnum, 30% pământ de frunze, 30% nisip [1]. Acest substrat este ideal pentru afine și azalee [2].");

dailyTips.put("02-26", "Tratează răsadurile cu soluție de vitamina B1 pentru stimularea sistemului radicular [1]. Concentrația optimă este 100mg/L aplicată la fiecare 2 săptămâni [2].");

dailyTips.put("02-27", "Pregătește soluție antifungică din bicarbonat de sodiu: 5g la 1L apă plus o picătură detergent [1]. Previne eficient mana și făinarea la răsaduri [2].");

dailyTips.put("02-28", "Planifică rotația culturilor pentru sezonul următor: evită plantarea aceluiași tip de legume în același loc 3 ani consecutiv [1]. Rotația previne epuizarea solului și acumularea dăunătorilor [2].");

// MARCH - 31 days
dailyTips.put("03-01", "Semănă roșiile pentru răsaduri în substrat sterilizat la temperatura de 22-25°C [1]. Folosește soiuri adaptate la climatul local pentru rezultate garantate [2].");

dailyTips.put("03-02", "Pregătește cadrul rece pentru aclimatizarea răsadurilor: asigură ventilație graduală [1]. Temperatura interioară să scadă treptat pentru întărirea plantelor [2].");

dailyTips.put("03-03", "Tratează solul cu gips agricol pentru îmbunătățirea structurii: 200g/m² pe soluri argiloase [1]. Gipsul ameliorează compactarea și îmbunătățește infiltrația apei [2].");

dailyTips.put("03-04", "Semănă ardeii și patenele în mini-sere la temperatura constantă de 25°C [1]. Semințele de ardei necesită călzură constantă pentru germinare uniformă [2].");

dailyTips.put("03-05", "Pregătește compostul rapid cu acceleratori naturali: adaugă bicarbonat de amoniu [1]. Procesul de compostare se reduce de la 6 luni la 3 luni [2].");

dailyTips.put("03-06", "Plantează cartofii timpurii în solul protejat de folii negre pentru încălzire [3]. Folia neagră absoarbe radiația solară și încălzește solul cu 3-5°C [1].");

dailyTips.put("03-07", "Semănă morcovii în rânduri distanțate la 25cm pentru ușurința întreținerii [1]. Amestecă semințele cu nisip fin pentru răspândire uniformă [2].");

dailyTips.put("03-08", "Tratează arborii fructiferi cu suspensie de var pentru protecția împotriva dăunătorilor [1]. Aplicația pe scoarță reflectă radiația și previne fisurarea [2].");

dailyTips.put("03-09", "Pregătește soluție nutritivă pentru hidroponie cu NPK 20-20-20 la 1g/L [1]. Monitorizează pH-ul soluției să rămână între 5.5-6.5 [2].");

dailyTips.put("03-10", "Semănă fasolea păstăi în ghivece biodegradabile pentru transplantare fără stres [1]. Rădăcinile fasole sunt sensibile la deranjare [2].");
dailyTips.put("03-11", "Verifică sămânța de fasole veche: înmoaie 10 boabe în apă 24h. Dacă mai puțin de 7 umflă, înlocuiește [1].");

dailyTips.put("03-12", "Plantează liliacul în sol bine drenat: groapa de 50x50cm cu strat de 10cm pietriș la fund [1]. Adaugă 1kg compost + 200g superfosfat/groapă [2].");

dailyTips.put("03-13", "Pregătește alifie pentru tăieturi la pomi: 1 parte ceară albine + 2 părți ulei de măsline [1]. Aplică pe rănile mai mari de 2cm [2].");

dailyTips.put("03-14", "Semănă rucola în vase adânci (minimum 20cm): 5 semințe/ghiveci la 1cm adâncime [1]. Recoltă frunzele când ating 10cm [2].");

dailyTips.put("03-15", "Tratează solul cu gips agricol pe terenuri argiloase: 200g/m² [1]. Îmbunătățește structura solului și infiltrația apei [2].");

dailyTips.put("03-16", "Plantează cartofii timpurii sub folie neagră: distanță 30cm între tuberculi [1]. Folia crește temperatura solului cu 3-5°C [2].");

dailyTips.put("03-17", "Semănă morcovii de primăvară în sol nisipos: adâncime 1cm, distanță între rânduri 25cm [1]. Alege soiuri rezistente la fisurare precum 'Nantes' [2].");

dailyTips.put("03-18", "Construiește un sistem de captare a apelor pluviale din jgheaburi: 1m² acoperiș = 1L apă/1mm ploaie [1].");

dailyTips.put("03-19", "Sărbătoarea Mărțișorului: plantează măceșii lângă pomii fructiferi pentru atragerea polenizatorilor [1].");

dailyTips.put("03-20", "Echilibrează pH-ul solului cu puiet de pădure: 5kg/m² pentru soluri acide [1]. Testează cu kit de pH lunar [2].");

dailyTips.put("03-21", "Semănă busuiocul sacru (Ocimum sanctum) în ghivece: temperatura minimă 15°C [1]. Folosește la ceaiuri medicinale [2].");

dailyTips.put("03-22", "Pregătește stratificare la semințele de măr: pune în frigider la 4°C pentru 60 zile în nisip umed [1].");

dailyTips.put("03-23", "Plantează zmeura în rânduri la 1.5m distanță [1]. Tăiește lăstarii la 30cm înălțime pentru înrădăcinare puternică [2].");

dailyTips.put("03-24", "Semănă pătrunjelul în sol umed: 0.5cm adâncime, 10cm între rânduri [1]. Recoltează după 75-90 zile [2].");

dailyTips.put("03-25", "Băltește răsadurile de roșii cu apă de ploaie: 20°C, o dată la 3 zile [1]. Evită udarea frunzelor [2].");

dailyTips.put("03-26", "Pivestește răsadurile de vinete: 12°C noaptea timp de 5 zile pentru adaptare la exterior [1].");

dailyTips.put("03-27", "Plantează salvie medicinală în zone însorite: pH 6.0-7.0 [1]. Taie tulpinile la 10cm înălțime pentru ramificare [2].");

dailyTips.put("03-28", "Semănă ridichile de vară direct în sol: 2cm adâncime, 5cm între plante [1]. Recoltează în 25-30 zile [2].");

dailyTips.put("03-29", "Aplică mușchi de turbă la baza trandafirilor: strat de 5cm pentru menținerea umidității [1].");

dailyTips.put("03-30", "Instalează plase împotriva păsărilor la serele cu răsaduri [1]. Folosește ochiuri de 2cm pentru eficiență maximă [2].");

dailyTips.put("03-31", "Verifică irigația prin picurare: presiune optimă 1.5-2.5 bar [1]. Curăță filtrele săptămânal [2].");

// APRIL - 30 days
dailyTips.put("04-01", "Plantează cartofii timpurii în solul încălzit peste 8°C: adâncime 10cm, distanță 30cm între tuberculi [1]. Acoperă cu folie neagră pentru protecție împotriva înghețului tardiv și încălzirea solului cu 3-5°C [7].");

dailyTips.put("04-02", "Semănă mazărea în rânduri duble la 15cm distanță: suportă temperaturi până la -5°C [1]. Instalează plase de 1.8m înălțime pentru soiurile de cățărătoare [2].");

dailyTips.put("04-03", "Pregătește soluție organică anti-păduchi: 200g săpun de Marsilia ras + 2L apă caldă [13]. Amestecă până se dizolvă și aplică cu pulverizator dimineața devreme [15].");

dailyTips.put("04-04", "Plantează ceapa de iarnă în solul bine drenat: pH 6.0-7.0, distanță 10cm între bulbi [1]. Adaugă 150g cenușă de lemn/m² pentru potasiu și prevenirea putregaiului [3].");

dailyTips.put("04-05", "Construiește paturi ridicate pentru seniori: înălțime 70-80cm, lățime maximă 120cm pentru accesibilitate [14]. Umple cu 40% compost, 30% sol vegetal, 30% nisip de râu [16].");

dailyTips.put("04-06", "Transplantează răsadurile de roșii în sere neîncălzite când temperatura nocturnă depășește 10°C [1]. Distanță 50cm între plante pentru circulația aerului [2].");

dailyTips.put("04-07", "Semănă morcovii de vară direct în sol: 1cm adâncime, amestecă semințele cu nisip fin pentru răspândire uniformă [1]. Distanță între rânduri 25cm [8].");

dailyTips.put("04-08", "Aplică bordelez la pomii fructiferi: 300g var stins + 300g sulfat de cupru la 10L apă [2]. Tratează când temperatura este peste 8°C pentru prevenirea manei și rugginii [3].");

dailyTips.put("04-09", "Plantează fasolea păstăi în ghivece biodegradabile pentru transplantare fără stres radicular [8]. Temperatura minimă de germinare: 12°C [1].");

dailyTips.put("04-10", "Începe sezonul de plantare directă: porumb, dovleci, castraveți când solul atinge 15°C [1]. Verifică prognoza meteo pentru următoarele 10 zile fără îngheț [7].");

dailyTips.put("04-11", "Instalează sistem de irigare prin picurare pentru legumele în sere: 2-4L apă/plantă/zi [3]. Programează udarea dimineața devreme pentru eficiență maximă [2].");

dailyTips.put("04-12", "Semănă salata în succesiuni la interval de 2 săptămâni pentru recoltă continuă [8]. Alege soiuri rezistente la montare timpurie pentru rezultate optime [2].");

dailyTips.put("04-13", "Pregătește amestec pentru mulciș organic: paie de grâu + rumeguș de lemn în părți egale [3]. Aplică strat de 7-10cm la baza roșiilor pentru reținerea umidității [2].");

dailyTips.put("04-14", "Plantează busuiocul lângă roșii pentru respingerea țânțarilor și îmbunătățirea aromei [13]. Temperatura minimă de plantare: 15°C [8].");

dailyTips.put("04-15", "Aplică îngrășământ organic la trandafiri: 2kg compost + 100g făină de oase/tufă [2]. Presară în jurul bazei și încorporează în primul strat de sol [3].");

dailyTips.put("04-16", "Semănă ardeii iuți în ghivece cu substrat drenat: pH 6.0-6.8 [1]. Folosește soiuri locale adaptate climatului continental românesc [7].");

dailyTips.put("04-17", "Construiește cadre reci portabile pentru protecția răsadurilor: orientare sud-est pentru lumina maximă [2]. Asigură ventilație reglabilă pentru zilele călduroase [3].");

dailyTips.put("04-18", "Plantează căpșuni în paturi ridicate cu mulciș de paie: distanță 30cm între plante [2]. Alege soiuri remontante pentru recoltă până în octombrie [3].");

dailyTips.put("04-19", "Tratează preventiv viața de vie cu soluție de bicarbonat: 5g/L apă pentru combaterea manei [15]. Aplică săptămânal până la înflorire [13].");

dailyTips.put("04-20", "Semănă spanacul de vară în umbra parțială pentru evitarea montării rapide [8]. Ud cu apă de ploaie pentru menținerea prospețimii frunzelor [2].");

dailyTips.put("04-21", "Instalează plase de protecție împotriva păsărilor la culturile de cereale [2]. Folosește ochiuri de 2cm pentru eficiență maximă fără a afecta polenizatorii [3].");

dailyTips.put("04-22", "Pregătește sol pentru plantarea dovlecilor: groapa 50x50cm cu 3kg compost + 200g cenușă [1]. Acoperă cu folie neagră pentru încălzire [7].");

dailyTips.put("04-23", "Sărbătoarea Sf. Gheorghe: plantează porumbul tradițional cu semințe moștente din anul trecut [9]. Îngroapă la 5cm adâncime în sol încălzit peste 12°C [1].");

dailyTips.put("04-24", "Semănă castraveții pentru conserve direct în sol: distanță 1m între plante [1]. Construiește spaliere înalte de 2m pentru soiurile de cățărătoare [8].");

dailyTips.put("04-25", "Aplică tratament foliar cu extract de urzici fermentate: diluție 1:10 [13]. Îmbunătățește rezistența plantelor la dăunători și boli fungice [15].");

dailyTips.put("04-26", "Plantează ierburi aromatice în ghivece pe pervaz: rozmarin, cimbru, oregano [2]. Folosește substrat cu 30% nisip pentru drenaj excelent [16].");

dailyTips.put("04-27", "Semănă floarea-soarelui în sol adânc și fertil: adâncime 3cm, distanță 50cm [2]. Protejează semințele de păsări cu plasă fină primele 2 săptămâni [3].");

dailyTips.put("04-28", "Verifică și curăță sistemele de drenaj la ghivecele cu plante perene [16]. Înlocuiește stratul superior de substrat cu compost proaspăt [14].");

dailyTips.put("04-29", "Plantează napi pentru conserve de iarnă: semințe la 1cm adâncime, recoltă în septembrie [8]. Alege soiuri cu rădăcini mari pentru depozitare îndelungată [1].");

dailyTips.put("04-30", "Pregătește grădina pentru luna mai: verifică toate sistemele de irigare și repară scurgerile [3]. Planifică plantările succesive pentru recoltă continuă [2].");

// MAY - 31 days
dailyTips.put("05-01", "Sărbătoarea Armindenului: plantează usturoiul românesc roșu (Allium sativum 'Romanian Red') în sol bine drenat, pH 6.5-7.0 [1]. Distanță 15cm între căței, adâncime 5cm cu vârful în sus [5].");

dailyTips.put("05-02", "Pregătește soluție anti-păduchi: 2 linguri săpun de Marsilia + 1L apă caldă [1]. Aplică dimineața pe frunzele atacate, evitând orele de soare puternic [3].");

dailyTips.put("05-03", "Plantează cartofii timpurii în zonele de deal (Zona 5-6): distanță 40cm între rânduri [2]. Acoperă cu paie pentru menținerea umidității [6].");

dailyTips.put("05-04", "Semănă fasolea păstăi direct în sol: temperatura minimă 12°C [3]. Folosește grămezi de 3-4 semințe la 50cm distanță [1].");

dailyTips.put("05-05", "Începe recoltarea rucolei sălbatice (Diplotaxis tenuifolia) pentru salate [7]. Taie doar 1/3 din frunze pentru regenerare rapidă [1].");

dailyTips.put("05-06", "Plantează roșiile în sere neîncălzite: distanță 60cm, adaugă 200g coajă de ouă zdrobită/groapă pentru calciu [3].");

dailyTips.put("05-07", "Construiește spaliere pentru castraveți din nuiele de salcie: înălțime 1.5m, distanță între suporturi 40cm [1].");

dailyTips.put("05-08", "Semănă floarea-soarelui 'Gigantul de Ungheni': 3cm adâncime, 50cm între plante [2]. Protejează semințele cu plasă împotriva păsărilor [6].");

dailyTips.put("05-09", "Aplică compost de urzici la trandafiri: 1L soluție diluată 1:10/plantă [1]. Îmbunătățește rezistența la mană și ruginiu [3].");

dailyTips.put("05-10", "Plantează ardei iute 'Gorilla' în zonele calde (Zona 7-8): temperatură nocturnă minimă 15°C [2]. Folosește mulch din paie de grâu [5].");

dailyTips.put("05-11", "Semănă salata de vară 'Lollo Rosso' în umbră parțială: distanță 25cm, udă la rădăcină dimineața [3].");

dailyTips.put("05-12", "Pregătește ceai de pelin pentru combaterea furnicilor: 100g frunze uscate la 1L apă fiartă [1]. Stropiște pe traseele lor [4].");

dailyTips.put("05-13", "Plantează busuioc sfânt (Ocimum sanctum) lângă uși și ferestre pentru protecție tradițională [4]. Temperatura minimă 10°C [5].");

dailyTips.put("05-14", "Semănă morcovii 'Nantes' în sol nisipos: adâncime 1cm, distanță 5cm între semințe [3]. Subțiază la 10cm după 3 săptămâni [6].");

dailyTips.put("05-15", "Pivestește răsadurile de vinete: 12°C noaptea timp de 5 zile înainte de plantare [1]. Folosește compost matur în gropi [2].");

dailyTips.put("05-16", "Plantează liliacul românesc (Syringa vulgaris) în sol calcaros: groapă 60x60cm cu 2kg compost [7].");

dailyTips.put("05-17", "Semănă mărarul pentru conserve: distanță 20cm între rânduri, recoltă la 60cm înălțime [1]. Usucă în mănunchiuri cu capul în jos [4].");

dailyTips.put("05-18", "Aplică tratament cu lapte împotriva manei la roșii: 1 parte lapte la 9 părți apă [3]. Stropiți săptămânal dimineața [1].");

dailyTips.put("05-19", "Plantează arpagicul în ghivece pe pervaz: substrat 30% nisip, udă când solul e uscat la atingere [6].");

dailyTips.put("05-20", "Semănă floarea-soarelui 'Titan' pentru semințe mari: temperatură sol 15°C, adâncime 4cm [5]. Distanță 70cm între plante [2].");

dailyTips.put("05-21", "Construiește un sistem de udare cu sticle reciclate: umple sticlele de 2L și îngroapă lângă plante cu găurile spre rădăcini [1].");

dailyTips.put("05-22", "Plantează salvie medicinală (Salvia officinalis) lângă ferestre: protejează împotriva țânțarilor [4]. Taie tulpinile la 10cm [3].");

dailyTips.put("05-23", "Semănă castraveții 'Dăbuleni' direct în sol: temperatură minimă 18°C noaptea [2]. Folosește mulch din paie pentru menținerea umidității [6].");

dailyTips.put("05-24", "Pregătește alifie pentru tăieturi la pomi: 1 parte ceară de albine + 2 părți ulei de măsline [1]. Aplică pe rănile mai mari de 2cm [3].");

dailyTips.put("05-25", "Plantează zmeura 'Heritage' în rânduri de 1.5m [2]. Tăiește lăstarii la 30cm pentru înrădăcinare puternică [6].");

dailyTips.put("05-26", "Semănă ridichile de vară 'Saxa' în umbră parțială: recoltă în 25 zile [3]. Amestecă semințele cu nisip pentru răspândire uniformă [1].");

dailyTips.put("05-27", "Aplică îngrășământ din banane fermentate: 3 bucăți la 10L apă timp de 3 zile [1]. Bogat în potasiu pentru înflorire abundentă [5].");

dailyTips.put("05-28", "Plantează menta 'Piperita' în ghivece izolate: substrat 40% nisip [1]. Taie tulpinile la 15cm pentru frunze mai mari [3].");

dailyTips.put("05-29", "Semănă porumbul dulce 'Târnava' în grămezi de 4-5 semințe: distanță 80cm între grămezi [2]. Temperatura sol minim 12°C [6].");

dailyTips.put("05-30", "Construiește un hotel pentru albine singuratice din trestie de râu: atașează la 1m înălțime, orientare sud-est [7].");

dailyTips.put("05-31", "Pregătește ghivece pentru iarnă: sterilizează cu alcool 70% și usucă la soare [1]. Adaugă strat drenant de 5cm pietriș [3].");
// JUNE - 30 days
dailyTips.put("06-01", "Sărbătoarea Învierii Domnului (Rusalii): plantează basilic sfânt pentru protecția casei. Folosește substrat bogat în humus cu pH 6.0-7.0. Temperatura minimă de plantare: 18°C.");
dailyTips.put("06-02", "Leagă roșiile de țăruși cu panglici moi de material textil: evită sârma care poate tăia tulpinile. Înălțime țăruși: 1.8m pentru soiurile nedeterminate.");
dailyTips.put("06-03", "Semănă fasolea boabe 'Alb de Ploiești' pentru conserve de iarnă: adâncime 4cm, distanță 15cm. Recoltă când păstăile sunt uscate pe plantă.");
dailyTips.put("06-04", "Pregătește soluție anti-păduchi din usturoi: 5 căței zdrobiți în 1L apă, lasă 24h. Stropiți dimineața devreme pentru eficiență maximă.");
dailyTips.put("06-05", "Recoltează primul spanac înainte de montare: taie frunzele exterioare la 2cm de sol. Plantele regenerează în 2-3 săptămâni pentru a doua recoltă.");
dailyTips.put("06-06", "Plantează castraveții 'Cornichon de Paris' pentru murături: distanță 50cm, spaliere înalte de 1.5m. Udă zilnic cu 2L apă/plantă.");
dailyTips.put("06-07", "Construiește umbrar pentru salata de vară din plasă de 50% opacitate. Orientează sud-vest pentru protecția în orele de după-amiază.");
dailyTips.put("06-08", "Semănă morcovii 'Chantenay' pentru iarnă: sol adânc de 25cm, fără pietre. Recoltă în octombrie pentru depozitare.");
dailyTips.put("06-09", "Aplică mulch din paie la baza roșiilor: strat de 8cm pentru menținerea umidității constante. Evită contactul cu tulpina pentru prevenirea putregaiului.");
dailyTips.put("06-10", "Începe recoltarea ridichilor 'Cherry Belle': diametru optimal 2-3cm. Consumă în maxim 3 zile pentru prospețime.");
dailyTips.put("06-11", "Plantează cuișoarele (Dianthus caryophyllus) pentru parfumul intens. Sol calcaros, pH 7.0-8.0, drenaj excelent.");
dailyTips.put("06-12", "Semănă dovleacul 'Muscat de Provence' pentru Halloween: gropi 50x50cm cu 3kg compost. Distanță 2m între plante.");
dailyTips.put("06-13", "Pregătește ceai de cozi de ceapă pentru întărirea plantelor: 100g cozi la 1L apă fiartă. Lasă să se răcească și udă cu soluția diluată 1:5.");
dailyTips.put("06-14", "Îndepărtează lăstarii de la roșii săptămânal: rupture cu degetele la 5cm lungime. Operația se face dimineața când plantele sunt hidratate.");
dailyTips.put("06-15", "Recoltează cireșele timpurii 'Cătălina': în zori când sunt răcoroase pentru păstrare îndelungată. Folosește coș căptușit cu foi de cireș.");
dailyTips.put("06-16", "Semănă salata 'Ice Berg' pentru vara târzie: în umbră parțială, udă cu apă rece. Substratul să fie mereu umed dar nu ud.");
dailyTips.put("06-17", "Plantează lavanda 'Hidcote Blue' pe marginile aleilor: distanță 40cm, sol nisipos. Taie tulpinile după înflorire pentru formă compactă.");
dailyTips.put("06-18", "Construiește capcane pentru limacsi din bere românească în pahare îngropate. Înlocuiește berea la 3 zile pentru eficiență.");
dailyTips.put("06-19", "Semănă varza de iarnă 'Brăila' în răsadniță: substrat sterilizat, temperatura 16-18°C. Transplantează în august.");
dailyTips.put("06-20", "Solstițiul de vară: recoltează plante medicinale la puterea maximă. Mușețelul, mentă și salvie se usucă în mănunchiuri.");
dailyTips.put("06-21", "Udă grădina seara târziu (după ora 20) pentru evaporare minimă. Folosește aspersoare cu picături mari pentru penetrare profundă.");
dailyTips.put("06-22", "Plantează porumbul dulce 'Golden Bantam' pentru toamnă: ultimă șansă în zonele calde. Protejează cu plasă împotriva păsărilor.");
dailyTips.put("06-23", "Pregătește extract de pelin pentru combaterea moliilor: 300g frunze proaspete la 3L apă. Fierbe 20 minute, strecoară și stropiți seara.");
dailyTips.put("06-24", "Sărbătoarea Sânzienelor: culege ierburi magice (sunătoare, coada șoricelului) în această noapte. Usucă pentru ceaiuri de iarnă.");
dailyTips.put("06-25", "Recoltează castraveții tineri pentru murături: lungime 5-7cm, culoare verde intens. Culeagerea zilnică stimulează producția.");
dailyTips.put("06-26", "Semănă napi pentru conserve 'Purple Top': sol profund, udare abundentă. Recoltă în septembrie când au 8-10cm diametru.");
dailyTips.put("06-27", "Aplică îngrășământ din compost de alge marine la roșii: 200g/plantă. Bogat în microelemente pentru fructe aromate.");
dailyTips.put("06-28", "Construiește sistem de umbrar mobil din țăruși și pânză. Protejează răsadurile în orele 12-16 când radiația e intensă.");
dailyTips.put("06-29", "Plantează celina pentru rădăcini mari: sol adânc de 30cm, bogat în humus. Distanță 30cm între plante pentru dezvoltare optimă.");
dailyTips.put("06-30", "Pregătește grădina pentru iulie: verifică irigația, repară mulchiul și planifică a doua recoltă. Comandă semințe pentru culturile de toamnă.");

// JULY - 31 days
dailyTips.put("07-01", "Recoltează usturoiul când frunzele de jos se îngălbenesc. Scoate-l cu furca, lasă-l să se usuce la umbră și aer curat timp de 2 săptămâni, apoi curăță și păstrează-l în loc răcoros.");
dailyTips.put("07-02", "Udă roșiile la rădăcină, nu pe frunze, pentru a preveni mana. Udarea se face dimineața devreme, folosind 2-3 litri de apă/plantă la fiecare 3 zile.");
dailyTips.put("07-03", "Aplică paie sau frunze uscate ca mulci sub ardei și vinete. Mulciul păstrează solul răcoros, reduce buruienile și menține umiditatea.");
dailyTips.put("07-04", "Îndepărtează lăstarii laterali (copili) de la roșiile nedeterminate. Rupe-i cu mâna, dimineața, când sunt fragede.");
dailyTips.put("07-05", "Verifică zilnic castraveții pentru fructe gata de cules. Culege-i la 10-12 cm lungime pentru a stimula producția continuă.");
dailyTips.put("07-06", "Aplică tratament cu lapte (1 parte lapte la 9 părți apă) pe frunzele de dovlecei și castraveți pentru prevenirea făinării.");
dailyTips.put("07-07", "Recoltează ceapa când frunzele încep să se culce. Scoate bulbii, usucă-i 2 săptămâni la umbră, apoi curăță și depozitează în plase aerisite.");
dailyTips.put("07-08", "Plantează varza de toamnă în grădină. Folosește răsaduri viguroase, distanță de 50cm între plante și 60cm între rânduri.");
dailyTips.put("07-09", "Verifică pomii fructiferi pentru fructe bolnave sau putrezite. Îndepărtează-le imediat pentru a preveni răspândirea bolilor.");
dailyTips.put("07-10", "Udă salata și spanacul dimineața devreme. În zilele caniculare, protejează-le cu un umbrar improvizat din pânză albă.");
dailyTips.put("07-11", "Taie lăstarii de zmeur care au rodit, la nivelul solului, pentru a stimula creșterea lăstarilor noi și recolta de anul viitor.");
dailyTips.put("07-12", "Aplică îngrășământ lichid din compost la roșii și ardei. Diluează 1 litru de compost lichid în 10 litri de apă și udă la rădăcină.");
dailyTips.put("07-13", "Verifică plantele de cartof pentru gândacul de Colorado. Adună manual adulții și larvele, sau folosește ceai de pelin ca tratament natural.");
dailyTips.put("07-14", "Culege plantele aromatice (busuioc, mentă, oregano) dimineața, înainte de a înflori, pentru a păstra aroma intensă la uscare.");
dailyTips.put("07-15", "Plantează fasolea verde pentru recolta de toamnă. Alege soiuri cu maturitate rapidă și udă regulat pentru germinare bună.");
dailyTips.put("07-16", "Îndepărtează frunzele inferioare bolnave de la roșii și ardei. Ajută la circulația aerului și previne răspândirea bolilor.");
dailyTips.put("07-17", "Aplică mulci de iarbă tăiată sub dovleci și pepeni pentru a păstra solul umed și a preveni contactul fructelor cu pământul.");
dailyTips.put("07-18", "Verifică sistemul de irigație. Curăță filtrele și duzele pentru a asigura o udare uniformă.");
dailyTips.put("07-19", "Recoltează dovleceii când au 15-20cm lungime. Culegerea timpurie stimulează apariția altor fructe.");
dailyTips.put("07-20", "Plantează ridichi de toamnă și sfeclă roșie pentru recoltă la sfârșit de septembrie. Udă constant pentru rădăcini crocante.");
dailyTips.put("07-21", "Verifică tufele de coacăze și agrișe pentru fructe coapte. Culege-le dimineața, când sunt răcoroase, pentru păstrare mai bună.");
dailyTips.put("07-22", "Taie vârfurile lăstarilor de castraveți pentru a stimula ramificarea și producția de fructe.");
dailyTips.put("07-23", "Aplică tratament cu macerat de urzici (1:10) la roșii și vinete pentru a preveni carențele de azot.");
dailyTips.put("07-24", "Verifică trandafirii pentru păduchi verzi. Îndepărtează-i manual sau stropește cu apă cu săpun natural.");
dailyTips.put("07-25", "Recoltează usturoiul de toamnă. Lasă-l la uscat în șiraguri, la umbră, pentru păstrare pe termen lung.");
dailyTips.put("07-26", "Plantează prazul pentru recoltă de toamnă. Folosește răsaduri sănătoase, distanță 15cm între plante.");
dailyTips.put("07-27", "Udă pepenii verzi și galbeni la rădăcină, dimineața devreme. Evită udarea frunzelor pentru a preveni bolile fungice.");
dailyTips.put("07-28", "Îndepărtează lăstarii de la vița de vie care nu poartă ciorchini. Ajută la maturarea strugurilor și la aerisirea butucului.");
dailyTips.put("07-29", "Culege semințe de salată și ridichi pentru anul următor. Uscă-le bine și păstrează-le în pungi de hârtie la răcoare.");
dailyTips.put("07-30", "Aplică tratament cu zeamă bordeleză (sulfat de cupru + var stins) la roșii și cartofi pentru prevenirea manei.");
dailyTips.put("07-31", "Pregătește grădina pentru culturile de toamnă: sapă adânc, adaugă compost proaspăt și planifică rotația culturilor.");

// AUGUST - 31 days
dailyTips.put("08-01", "Recoltează roșiile coapte dimineața devreme pentru aroma maximă. Nu păstra roșiile la frigider, ci într-un loc răcoros și aerisit.");
dailyTips.put("08-02", "Verifică plantele de dovlecei și castraveți pentru fructe ascunse. Culegerea la timp stimulează producția continuă.");
dailyTips.put("08-03", "Aplică mulci proaspăt sub ardei și vinete pentru a păstra solul răcoros în zilele caniculare.");
dailyTips.put("08-04", "Plantează varza de toamnă și broccoli pentru recoltă în octombrie. Folosește răsaduri sănătoase și udă constant.");
dailyTips.put("08-05", "Udă grădina dimineața devreme sau seara târziu pentru a reduce evaporarea apei și stresul termic la plante.");
dailyTips.put("08-06", "Verifică pomii fructiferi pentru fructe căzute sau bolnave. Îndepărtează-le pentru a preveni răspândirea bolilor.");
dailyTips.put("08-07", "Plantează spanac pentru cultură de toamnă. Semănă direct în sol, în rânduri la 20cm distanță.");
dailyTips.put("08-08", "Aplică tratament cu macerat de coada-calului (1:5) la roșii și castraveți pentru prevenirea bolilor fungice.");
dailyTips.put("08-09", "Recoltează ceapa și usturoiul de toamnă. Lasă-le la uscat în aer liber, la umbră, timp de 2 săptămâni.");
dailyTips.put("08-10", "Îndepărtează frunzele bolnave sau îngălbenite de la plantele de tomate și ardei pentru a preveni răspândirea bolilor.");
dailyTips.put("08-11", "Semănă ridichi de toamnă pentru recoltă în septembrie-octombrie. Udă constant pentru rădăcini crocante.");
dailyTips.put("08-12", "Verifică plantele de fasole pentru păstăi uscate. Culege-le și păstrează semințele pentru anul viitor.");
dailyTips.put("08-13", "Aplică tratament cu lapte (1:9 cu apă) pe frunzele de dovlecei pentru prevenirea făinării.");
dailyTips.put("08-14", "Plantează salată de toamnă și andive pentru recoltă târzie. Semănă în locuri semiumbrite.");
dailyTips.put("08-15", "Sărbătoarea Adormirii Maicii Domnului: plantează usturoi pentru recoltă timpurie anul viitor.");
dailyTips.put("08-16", "Verifică sistemul de irigare și curăță filtrele pentru a preveni blocajele.");
dailyTips.put("08-17", "Recoltează plantele aromatice (busuioc, mentă, cimbru) pentru uscare. Leagă-le în buchete și atârnă-le la umbră.");
dailyTips.put("08-18", "Plantează praz pentru recoltă de toamnă târzie. Folosește răsaduri viguroase și udă regulat.");
dailyTips.put("08-19", "Aplică mulci de paie sub pepeni și dovleci pentru a preveni contactul direct cu solul și apariția putregaiului.");
dailyTips.put("08-20", "Verifică plantele de cartof pentru gândacul de Colorado. Adună manual adulții și larvele.");
dailyTips.put("08-21", "Udă răsadurile de varză și broccoli regulat pentru a preveni stresul hidric și apariția gustului amar.");
dailyTips.put("08-22", "Plantează spanac și salată pentru cultură de toamnă. Semănă în sol umed și fertil.");
dailyTips.put("08-23", "Aplică tratament cu zeamă bordeleză la vița de vie pentru prevenirea manei.");
dailyTips.put("08-24", "Recoltează ardeii grași când sunt bine colorați și tari la atingere. Culegerea la timp stimulează apariția altor fructe.");
dailyTips.put("08-25", "Îndepărtează frunzele inferioare de la roșii pentru a îmbunătăți circulația aerului și a preveni bolile.");
dailyTips.put("08-26", "Semănă morcovi de toamnă pentru recoltă târzie. Alege soiuri cu maturitate rapidă.");
dailyTips.put("08-27", "Verifică trandafirii pentru păduchi verzi. Îndepărtează-i manual sau folosește apă cu săpun natural.");
dailyTips.put("08-28", "Aplică îngrășământ lichid din compost la ardei și vinete. Udă la rădăcină, evitând frunzele.");
dailyTips.put("08-29", "Recoltează semințe de dovleac pentru anul viitor. Spală-le, usucă-le și păstrează-le la răcoare.");
dailyTips.put("08-30", "Plantează varză de Bruxelles pentru recoltă târzie. Alege un loc însorit și sol bogat în humus.");
dailyTips.put("08-31", "Pregătește grădina pentru toamnă: adaugă compost proaspăt, sapă adânc și planifică rotația culturilor.");

// SEPTEMBER - 30 days
dailyTips.put("09-01", "Recoltează ceapa și usturoiul de toamnă. Lasă-le la uscat în aer liber, la umbră, timp de 2 săptămâni pentru păstrare pe termen lung.");
dailyTips.put("09-02", "Plantează spanac și salată pentru cultură de toamnă. Semănă direct în sol, în rânduri la 20cm distanță, și udă constant.");
dailyTips.put("09-03", "Aplică compost proaspăt pe straturi goale pentru a îmbogăți solul înainte de iarnă.");
dailyTips.put("09-04", "Verifică pomii fructiferi pentru fructe bolnave sau căzute. Îndepărtează-le pentru a preveni răspândirea bolilor.");
dailyTips.put("09-05", "Plantează usturoi pentru recoltă timpurie anul viitor. Alege căței mari, sănătoși, și plantează-i la 5cm adâncime.");
dailyTips.put("09-06", "Udă răsadurile de varză și broccoli regulat pentru a preveni stresul hidric și apariția gustului amar.");
dailyTips.put("09-07", "Recoltează semințele de fasole, mazăre și dovleac pentru anul viitor. Uscă-le bine și păstrează-le la răcoare.");
dailyTips.put("09-08", "Sărbătoarea Nașterii Maicii Domnului: plantează narcise și lalele pentru flori timpurii în primăvară.");
dailyTips.put("09-09", "Aplică tratament cu zeamă bordeleză la vița de vie pentru prevenirea manei.");
dailyTips.put("09-10", "Plantează ridichi de toamnă pentru recoltă în octombrie. Udă constant pentru rădăcini crocante.");
dailyTips.put("09-11", "Verifică plantele de tomate și ardei pentru fructe bolnave sau putrezite. Îndepărtează-le imediat.");
dailyTips.put("09-12", "Aplică mulci proaspăt pe straturile de legume pentru a menține umiditatea și a preveni buruienile.");
dailyTips.put("09-13", "Recoltează merele și perele când sunt tari și au culoarea specifică soiului. Depozitează-le în lăzi aerisite la răcoare.");
dailyTips.put("09-14", "Plantează varză de Bruxelles pentru recoltă târzie. Alege un loc însorit și sol bogat în humus.");
dailyTips.put("09-15", "Verifică sistemul de irigare și curăță filtrele pentru a preveni blocajele.");
dailyTips.put("09-16", "Plantează spanac și salată pentru cultură de toamnă târzie. Semănă în sol umed și fertil.");
dailyTips.put("09-17", "Aplică tratament cu macerat de urzici (1:10) la legume pentru a preveni carențele de azot.");
dailyTips.put("09-18", "Recoltează plantele aromatice (busuioc, mentă, cimbru) pentru uscare. Leagă-le în buchete și atârnă-le la umbră.");
dailyTips.put("09-19", "Plantează usturoi și ceapă de toamnă pentru recoltă timpurie anul viitor.");
dailyTips.put("09-20", "Udă răsadurile de varză și broccoli regulat pentru a preveni stresul hidric și apariția gustului amar.");
dailyTips.put("09-21", "Aplică îngrășământ lichid din compost la ardei și vinete. Udă la rădăcină, evitând frunzele.");
dailyTips.put("09-22", "Recoltează semințe de morcov și pătrunjel pentru anul viitor. Uscă-le bine și păstrează-le la răcoare.");
dailyTips.put("09-23", "Plantează narcise și lalele pentru flori timpurii în primăvară. Alege bulbi sănătoși și plantează-i la 10cm adâncime.");
dailyTips.put("09-24", "Verifică plantele de cartof pentru gândacul de Colorado. Adună manual adulții și larvele.");
dailyTips.put("09-25", "Aplică tratament cu lapte (1:9 cu apă) pe frunzele de dovlecei pentru prevenirea făinării.");
dailyTips.put("09-26", "Plantează praz pentru recoltă de toamnă târzie. Folosește răsaduri viguroase și udă regulat.");
dailyTips.put("09-27", "Recoltează ardeii grași când sunt bine colorați și tari la atingere. Culegerea la timp stimulează apariția altor fructe.");
dailyTips.put("09-28", "Aplică mulci de paie sub pepeni și dovleci pentru a preveni contactul direct cu solul și apariția putregaiului.");
dailyTips.put("09-29", "Pregătește grădina pentru iarnă: adaugă compost proaspăt, sapă adânc și planifică rotația culturilor.");
dailyTips.put("09-30", "Verifică și curăță sistemele de drenaj la ghivecele cu plante perene. Înlocuiește stratul superior de substrat cu compost proaspăt.");

// OCTOBER - 31 days
dailyTips.put("10-01", "Plantează usturoiul pentru recolta de anul viitor. Folosește căței mari, sănătoși, la 5-7cm adâncime și 10-15cm distanță între ei.");
dailyTips.put("10-02", "Recoltează ultimele roșii și ardei înainte de primul îngheț. Pune fructele verzi la coacere în casă, la lumină indirectă.");
dailyTips.put("10-03", "Adună frunzele căzute și folosește-le ca mulci sau pentru compost. Nu arde frunzele – compostul îmbogățește solul.");
dailyTips.put("10-04", "Plantează ceapa și șalota de toamnă. Bulbii vor prinde rădăcini înainte de iarnă și vor da recolte timpurii în primăvară.");
dailyTips.put("10-05", "Curăță și taie plantele anuale uscate (busuioc, mărar, fasole). Resturile sănătoase pot merge la compost.");
dailyTips.put("10-06", "Plantează narcise, lalele, zambile și alte bulboase pentru flori timpurii în primăvară. Adâncime: 2-3 ori înălțimea bulbului.");
dailyTips.put("10-07", "Aplică un strat gros de mulci (paie, frunze, compost) pe straturile goale pentru a proteja solul de îngheț și eroziune.");
dailyTips.put("10-08", "Împarte și replantează tufele de perene (crini, stânjenei, bujori). Folosește unelte curate și plantează în sol fertil.");
dailyTips.put("10-09", "Recoltează dovlecii și depozitează-i într-un loc uscat și răcoros. Lasă tulpina de 5cm pentru o păstrare mai bună.");
dailyTips.put("10-10", "Verifică și repară sistemul de irigație. Golește furtunurile și depozitează-le la adăpost pentru a evita înghețarea.");
dailyTips.put("10-11", "Plantează spanac și salată de iarnă în solar sau sub folie. Acestea rezistă la frig și pot fi recoltate până la primăvară.");
dailyTips.put("10-12", "Taie ramurile uscate sau bolnave de la pomii fructiferi. Arde sau compostă resturile pentru a preveni bolile.");
dailyTips.put("10-13", "Pregătește locul pentru pomi fructiferi noi: sapă gropi de 50x50cm, adaugă compost și lasă-le să se așeze până la plantare.");
dailyTips.put("10-14", "Învelește trandafirii cu pământ și paie la bază pentru a-i proteja de ger. Poți folosi și frunze uscate sau compost.");
dailyTips.put("10-15", "Plantează varză de Bruxelles și kale pentru recoltă târzie. Acestea rezistă la frig și devin mai dulci după brumă.");
dailyTips.put("10-16", "Verifică depozitele de cartofi și rădăcinoase. Îndepărtează orice tubercul cu semne de putrezire pentru a proteja restul recoltei.");
dailyTips.put("10-17", "Adaugă compost proaspăt pe straturile goale pentru a îmbogăți solul peste iarnă.");
dailyTips.put("10-18", "Plantează panseluțe și crizanteme pentru culoare în grădină toată toamna și chiar iarna, dacă nu e ger puternic.");
dailyTips.put("10-19", "Verifică și curăță uneltele de grădină. Ascuțirea și ungerea lor le prelungește viața și ușurează munca la primăvară.");
dailyTips.put("10-20", "Plantează răsaduri de salată și spanac în ghivece mari pentru recoltă pe balcon sau terasă.");
dailyTips.put("10-21", "Protejează plantele sensibile (leandru, dafin, mușcate) mutându-le în interior sau în spații ferite de îngheț.");
dailyTips.put("10-22", "Aplică zeamă bordeleză la vița de vie și pomi pentru prevenirea bolilor fungice peste iarnă.");
dailyTips.put("10-23", "Recoltează semințe de flori și legume pentru anul viitor. Uscă-le bine și păstrează-le la răcoare, în pungi de hârtie.");
dailyTips.put("10-24", "Taie lăstarii de zmeur care au rodit, la nivelul solului, pentru a stimula creșterea lăstarilor noi.");
dailyTips.put("10-25", "Verifică gardurile și suporturile pentru plante cățărătoare. Repară-le înainte de iarnă pentru a evita pagubele.");
dailyTips.put("10-26", "Aplică un strat de compost sau gunoi de grajd bine descompus la baza pomilor și arbuștilor fructiferi.");
dailyTips.put("10-27", "Plantează mazăre și bob pentru recoltă timpurie la primăvară. Acoperă semințele cu un strat de frunze pentru protecție.");
dailyTips.put("10-28", "Recoltează ultimele vinete și dovlecei. Plantele pot fi scoase și compostate după recoltare.");
dailyTips.put("10-29", "Pregătește compostul pentru iarnă: întoarce grămada și acoperă cu folie sau paie pentru a păstra căldura.");
dailyTips.put("10-30", "Plantează arpagic și usturoi de toamnă în zonele cu ierni blânde. Acoperă cu mulci pentru protecție.");
dailyTips.put("10-31", "Notează ce a mers bine și ce nu în grădina din acest an. Planificarea din timp ajută la un sezon viitor mai productiv.");

// NOVEMBER - 30 days
dailyTips.put("11-01", "Plantează bulbi de lalele, narcise și zambile pentru flori timpurii în primăvară. Asigură o adâncime de plantare de 2-3 ori înălțimea bulbului.");
dailyTips.put("11-02", "Curăță grădina de resturi vegetale și frunze bolnave. Compostează doar resturile sănătoase pentru a evita răspândirea bolilor.");
dailyTips.put("11-03", "Aplică un strat gros de mulci (paie, frunze, compost) pe straturile goale pentru a proteja solul de îngheț și eroziune.");
dailyTips.put("11-04", "Verifică depozitele de cartofi, ceapă și rădăcinoase. Îndepărtează orice tubercul cu semne de putrezire.");
dailyTips.put("11-05", "Plantează usturoi și ceapă de toamnă în zonele cu ierni blânde. Acoperă cu mulci pentru protecție suplimentară.");
dailyTips.put("11-06", "Mută plantele sensibile (mușcate, leandru, dafin) în interior sau în spații ferite de îngheț.");
dailyTips.put("11-07", "Verifică și repară gardurile și suporturile pentru plante cățărătoare înainte de iarnă.");
dailyTips.put("11-08", "Aplică zeamă bordeleză la vița de vie și pomi pentru prevenirea bolilor fungice peste iarnă.");
dailyTips.put("11-09", "Recoltează ultimele mere și pere. Depozitează-le în lăzi aerisite, la răcoare, pentru păstrare îndelungată.");
dailyTips.put("11-10", "Împarte și replantează tufele de perene (crini, bujori, stânjenei) pentru întinerirea și înmulțirea lor.");
dailyTips.put("11-11", "Verifică și curăță uneltele de grădină. Ascuțirea și ungerea lor previne ruginirea și le prelungește viața.");
dailyTips.put("11-12", "Acoperă straturile de căpșuni cu paie sau frunze uscate pentru protecție împotriva gerului.");
dailyTips.put("11-13", "Plantează arpagic și usturoi de toamnă dacă nu ai făcut-o încă. Alege căței mari și sănătoși.");
dailyTips.put("11-14", "Aplică compost proaspăt pe straturile goale pentru a îmbogăți solul peste iarnă.");
dailyTips.put("11-15", "Recoltează și usucă plantele aromatice rămase (mentă, busuioc, cimbru) pentru ceaiuri și condimente de iarnă.");
dailyTips.put("11-16", "Verifică și curăță sistemele de drenaj la ghivecele cu plante perene.");
dailyTips.put("11-17", "Taie ramurile uscate sau bolnave de la pomii fructiferi. Arde sau compostă resturile pentru a preveni bolile.");
dailyTips.put("11-18", "Aplică un strat de compost sau gunoi de grajd bine descompus la baza pomilor și arbuștilor fructiferi.");
dailyTips.put("11-19", "Protejează trandafirii cu pământ și paie la bază pentru a-i feri de ger.");
dailyTips.put("11-20", "Plantează panseluțe și crizanteme pentru culoare în grădină toată toamna și chiar iarna, dacă nu e ger puternic.");
dailyTips.put("11-21", "Notează ce a mers bine și ce nu în grădina din acest an. Planificarea din timp ajută la un sezon viitor mai productiv.");
dailyTips.put("11-22", "Curăță și depozitează furtunurile de irigație pentru a evita înghețarea și deteriorarea lor.");
dailyTips.put("11-23", "Verifică depozitele de semințe. Uscă-le bine și păstrează-le la răcoare, în pungi de hârtie.");
dailyTips.put("11-24", "Aplică zeamă bordeleză la pomii fructiferi pentru protecție împotriva bolilor fungice peste iarnă.");
dailyTips.put("11-25", "Întoarce compostul pentru a accelera descompunerea și a obține un îngrășământ bogat la primăvară.");
dailyTips.put("11-26", "Verifică și curăță adăposturile pentru unelte și materiale de grădinărit.");
dailyTips.put("11-27", "Adună și depozitează frunzele uscate pentru mulci sau compost la primăvară.");
dailyTips.put("11-28", "Plantează bulbi de flori de toamnă (lalele, narcise, zambile) dacă vremea permite.");
dailyTips.put("11-29", "Protejează plantele sensibile de pe balcon sau terasă cu folie sau material textil special pentru iarnă.");
dailyTips.put("11-30", "Odihnește-te, bucură-te de recolta strânsă și pregătește planurile pentru grădina de anul viitor.");

// DECEMBER - 31 days
dailyTips.put("12-01", "Protejează ghivecele și plantele sensibile de îngheț: mută-le la adăpost sau învelește-le cu folie sau pături groase. Rădăcinile în ghiveci sunt mai expuse frigului decât cele din pământ.");
dailyTips.put("12-02", "Răzuiește și adună frunzele căzute de pe gazon și straturi. Folosește-le pentru a face compost sau leaf-mould, un îngrășământ excelent pentru anul viitor.");
dailyTips.put("12-03", "Verifică depozitele de cartofi, ceapă și rădăcinoase. Îndepărtează orice tubercul cu semne de putrezire pentru a proteja restul recoltei.");
dailyTips.put("12-04", "Redu udarea la plantele de interior și la cele iernate în seră. Umezeala excesivă favorizează apariția mucegaiului și a bolilor.");
dailyTips.put("12-05", "Curăță și ascuțeste uneltele de grădină. Depozitează-le într-un loc uscat, ferit de îngheț, pentru a le păstra în stare bună.");
dailyTips.put("12-06", "Plantează pomi fructiferi și arbuști ornamentali cu rădăcină nudă, cât timp solul nu este înghețat. Adaugă compost în groapă pentru pornire viguroasă.");
dailyTips.put("12-07", "Prunează trandafirii cățărători și pomii fructiferi (măr, păr) cât timp sunt în repaus vegetativ. Îndepărtează ramurile bolnave sau uscate.");
dailyTips.put("12-08", "Verifică și curăță adăposturile pentru păsări și umple hrănitoarele. Păsările ajută la controlul dăunătorilor prin consumul larvelor ascunse în scoarță.");
dailyTips.put("12-09", "Plantează bulbi de lalele și narcise dacă vremea permite. O plantare târzie poate aduce flori mai târziu, dar tot vor înflori.");
dailyTips.put("12-10", "Folosește paie, frunze sau brad uscat pentru a proteja plantele perene și trandafirii de gerul iernii.");
dailyTips.put("12-11", "Verifică și repară gardurile și suporturile pentru plante cățărătoare înainte de ninsori abundente.");
dailyTips.put("12-12", "Taie și adună crengi de ilex, brad sau vâsc pentru decorațiuni festive naturale și pentru a aerisi tufele.");
dailyTips.put("12-13", "Ridică și împarte tufele de rubarbă, replantând secțiunile sănătoase în sol bogat în compost.");
dailyTips.put("12-14", "Pune paie sau brad la baza rădăcinilor de pătrunjel și țelină rămase în grădină pentru a le proteja de îngheț.");
dailyTips.put("12-15", "Verifică și curăță sistemele de drenaj la ghivecele cu plante perene. Înlocuiește stratul superior de substrat cu compost proaspăt.");
dailyTips.put("12-16", "Prunează vița de vie: taie ramurile laterale la 1-2 ochi de la tulpina principală pentru a stimula producția de struguri anul viitor.");
dailyTips.put("12-17", "Planifică rotația culturilor pentru anul viitor. Notează ce a mers bine și ce nu în grădina acestui an.");
dailyTips.put("12-18", "Verifică plantele de interior pentru dăunători (păianjeni roșii, afide). Șterge frunzele cu o cârpă umedă.");
dailyTips.put("12-19", "Folosește zilele mai calde pentru a aerisi sera sau spațiile protejate, prevenind astfel apariția mucegaiului.");
dailyTips.put("12-20", "Pregătește compostul pentru iarnă: întoarce grămada și acoperă cu folie sau paie pentru a păstra căldura.");
dailyTips.put("12-21", "Sărbătorește solstițiul de iarnă: aprinde o lumânare în grădină și bucură-te de liniștea naturii în repaus.");
dailyTips.put("12-22", "Verifică depozitele de semințe. Uscă-le bine și păstrează-le la răcoare, în pungi de hârtie.");
dailyTips.put("12-23", "Adună și depozitează frunzele uscate pentru mulci sau compost la primăvară.");
dailyTips.put("12-24", "Pregătește decorațiuni naturale pentru Crăciun: coronițe din crengi de brad, conuri și scorțișoară.");
dailyTips.put("12-25", "Bucură-te de sărbători! Admiră grădina și planifică noi proiecte pentru anul viitor.");
dailyTips.put("12-26", "Dacă vremea permite, sapă ușor solul în jurul pomilor pentru a aerisi rădăcinile.");
dailyTips.put("12-27", "Verifică și curăță adăposturile pentru unelte și materiale de grădinărit.");
dailyTips.put("12-28", "Întoarce compostul pentru a accelera descompunerea și a obține un îngrășământ bogat la primăvară.");
dailyTips.put("12-29", "Planifică achiziția de semințe și materiale pentru sezonul următor. Consultă cataloagele și fă o listă de dorințe.");
dailyTips.put("12-30", "Verifică plantele de interior și ajustează udarea în funcție de temperatură și umiditate.");
dailyTips.put("12-31", "Încheie anul cu recunoștință pentru roadele grădinii. Scrie-ți obiectivele pentru grădinăritul de anul viitor și bucură-te de familie!");

    }
    
    public String getTodaysTip() {
        SimpleDateFormat sdf = new SimpleDateFormat("MM-dd", Locale.US);
        String today = sdf.format(new Date());
        
        String tip = dailyTips.get(today);
        if (tip != null) {
            return tip;
        }
        
        // Fallback to general seasonal tips
        int month = Integer.parseInt(today.substring(0, 2));
        return getSeasonalTip(month);
    }
    
    private String getSeasonalTip(int month) {
        switch (month) {
            case 12:
            case 1:
            case 2:
                return "Iarna: Planifică grădina și îngrijește uneltele. Protejează plantele de ger.";
            case 3:
            case 4:
            case 5:
                return "Primăvara: Timpul semănatului și plantatului. Pregătește solul și seamănă!";
            case 6:
            case 7:
            case 8:
                return "Vara: Udă regulat și protejează plantele de caniculă. Recoltează legumele coapte.";
            case 9:
            case 10:
            case 11:
                return "Toamna: Recoltează și depozitează legumele. Pregătește grădina pentru iarnă.";
            default:
                return "Îngrijește grădina cu dragoste și răbdare!";
        }
    }
    
    public String getTipForDate(String date) {
        return dailyTips.get(date);
    }
    
    public void addTip(String date, String tip) {
        dailyTips.put(date, tip);
    }
    
    public Map<String, String> getAllTips() {
        return new HashMap<>(dailyTips);
    }
}