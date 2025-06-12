const APP_VERSION = '1.0.0';
const BASE_URL = 'https://gabeetzu-project.onrender.com/';
const settingsBtn     = document.getElementById('settingsBtn');
const helpBtn         = document.getElementById('helpBtn');
const socialBtn       = document.getElementById('socialBtn');
const premiumBtn      = document.getElementById('premiumBtn');
const inviteBtnMenu   = document.getElementById('inviteBtn');
const privacyBtn      = document.getElementById('privacyBtn');
const settingsModal   = document.getElementById('settingsModal');
const helpModal       = document.getElementById('helpModal');
const settingsCloseBtn= document.getElementById('settingsCloseBtn');
const helpCloseBtn    = document.getElementById('helpCloseBtn');
const fontSizeRange   = document.getElementById('fontSizeRange');

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('service-worker.js');
}

let lastQuestion = '';
let lastImage = null;
let deferredPrompt = null;
// generate a referral code on first load
if(!localStorage.getItem('ref_code')){
  const user = localStorage.getItem('user_name') || 'anon';
  const refCode = (user.replace(/\s+/g,'').toUpperCase().slice(0,5) + Math.floor(Math.random()*1000));
  localStorage.setItem('ref_code', refCode);
}
const Trophies = {
  list: [
    { id: 'first_question', text: '🏅 Prima întrebare', condition: () => UsageTracker.total === 1 },
    { id: 'first_image', text: '📸 Prima imagine', condition: () => UsageTracker.photo === 1 },
    { id: 'hundred_questions', text: '💯 100 întrebări', condition: () => UsageTracker.total === 100 },
    { id: 'first_friend', text: '🫂 Ai invitat un prieten', condition: () => localStorage.getItem('referred_friend') === 'true' }
  ],
  unlock(id) {
    const unlocked = JSON.parse(localStorage.getItem('trophies') || '[]');
    if (!unlocked.includes(id)) {
      unlocked.push(id);
      localStorage.setItem('trophies', JSON.stringify(unlocked));
      const toast = document.createElement('div');
      toast.className = 'toast';
      toast.textContent = '🏆 Trofeu deblocat: ' + this.get(id).text;
      document.body.appendChild(toast);
      setTimeout(() => toast.remove(), 4000);
    }
  },
  get(id) {
    return this.list.find(t => t.id === id);
  },
  checkAll() {
    for (const t of this.list) {
      if (t.condition()) this.unlock(t.id);
    }
  },
  render() {
    const unlocked = JSON.parse(localStorage.getItem('trophies') || '[]');
    const box = document.getElementById('trophies');
    if(box){
      box.innerHTML = unlocked.map(id => '<div>' + this.get(id).text + '</div>').join('');
    }
  }
};

function incStat(field){
  const stats = JSON.parse(localStorage.getItem('stats') || '{"q":0,"img":0}');
  stats[field] = (stats[field]||0)+1;
  localStorage.setItem('stats', JSON.stringify(stats));
}

// --- Referral Handling ---
const urlParams = new URLSearchParams(location.search);
const refFromUrl = urlParams.get('ref');
if(refFromUrl && !localStorage.getItem('referrer_code')){
  localStorage.setItem('referrer_code', refFromUrl);
  fetch(BASE_URL + 'log-referral.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      referrer: refFromUrl,
      device_hash: localStorage.getItem('device_hash'),
      joined: new Date().toISOString()
    })
  }).catch(()=>{});
}
if(getReferrerCode() && !localStorage.getItem('ref_bonus')){
  localStorage.setItem('ref_bonus','3');
}

function getReferrerCode(){
  return localStorage.getItem('referrer_code');
}

function markReferrerUsed(){
  localStorage.setItem('referrer_used','true');
}

function hasUsedReferrer(){
  return localStorage.getItem('referrer_used') === 'true';
}

function getReferralLink(){
  const code = localStorage.getItem('ref_code');
  return `https://gospodapp.netlify.app/?ref=${code}`;
}

function logUsage(action){
  const body = {
    device_hash: getDeviceHash(),
    version: APP_VERSION,
    platform: navigator.userAgent,
    action
  };
  const name = getUserName();
  if(name) body.user_name = name;
  fetch('https://gabeetzu-project.onrender.com/log-usage.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(body)
  }).catch(()=>{});
}

function getDeviceHash(){
  let hash = localStorage.getItem('device_hash');
  if(!hash){
    const uuid = (crypto.randomUUID && crypto.randomUUID()) || Math.random().toString(36).slice(2);
    const uaPart = navigator.userAgent.slice(0,20);
    hash = (uuid + '_' + uaPart).replace(/[^a-zA-Z0-9_-]/g,'');
    localStorage.setItem('device_hash', hash);
  }
  return hash;
}

class UsageTracker {
  constructor() {
    this.key = 'usage';
    this.FREE_TEXT_LIMIT = 3;
    this.FREE_PHOTO_LIMIT = 1;
    this.bonusKey = 'ref_bonus';
    this.bonus = parseInt(localStorage.getItem(this.bonusKey) || '0',10);
    this.load();
    this.resetIfNewDay();
  }
  load() {
    this.data = JSON.parse(localStorage.getItem(this.key) || '{}');
  }
  save() {
    localStorage.setItem(this.key, JSON.stringify(this.data));
  }
  resetIfNewDay() {
    const today = new Date().toISOString().slice(0,10);
    if (this.data.date !== today) {
      this.data.date = today;
      this.data.text = 0;
      this.data.image = 0;
      this.save();
    }
  }
  canMakeTextAPICall() {
    this.resetIfNewDay();
    if(this.bonus > 0) return true;
    return (this.data.text || 0) < this.FREE_TEXT_LIMIT;
  }
  recordTextAPICall() {
    this.resetIfNewDay();
    if(this.bonus > 0){
      this.bonus--;
      localStorage.setItem(this.bonusKey, this.bonus.toString());
    } else {
      this.data.text = (this.data.text || 0) + 1;
      this.save();
    }
  }
  canMakeImageAPICall() {
    this.resetIfNewDay();
    if(this.bonus > 0) return true;
    return (this.data.image || 0) < this.FREE_PHOTO_LIMIT;
  }
  recordImageAPICall() {
    this.resetIfNewDay();
    if(this.bonus > 0){
      this.bonus--;
      localStorage.setItem(this.bonusKey, this.bonus.toString());
    } else {
      this.data.image = (this.data.image || 0) + 1;
      this.save();
    }
  }
  status() {
    const bonusTxt = this.bonus > 0 ? `, Bonus: ${this.bonus}` : '';
    return `Întrebări: ${this.data.text||0}/${this.FREE_TEXT_LIMIT}, Foto: ${this.data.image||0}/${this.FREE_PHOTO_LIMIT}${bonusTxt}`;
  }

  static get total(){
    const s = JSON.parse(localStorage.getItem('stats') || '{"q":0,"img":0}');
    return s.q;
  }

  static get photo(){
    const s = JSON.parse(localStorage.getItem('stats') || '{"q":0,"img":0}');
    return s.img;
  }
}

class DailyTipProvider {
  constructor() {
    this.tips = {};
    this.addDefaultTips();
  }
  addDefaultTips() {
this.tips["01-01"] = "Planifică rotația culturilor pentru noul an. Schimbă locul plantelor din aceeași familie (solanacee, cucurbitacee) pentru a preveni epuizarea solului.";
this.tips["01-02"] = "Pregătește compostul pentru primăvară: amestecă 3 straturi de resturi vegetale cu 1 strat de gunoi de grajd vechi de 6 luni.";
this.tips["01-03"] = "Verifică sămânța de roșii veche: pune 10 semințe pe șervețel umed. Dacă mai puțin de 7 germinează, cumpară altele.";
this.tips["01-04"] = "Tăiați vița de vie până la 15 ianuarie. Folosește foarfecă deosebită și dezinfectează cu alcool după fiecare tăietură.";
this.tips["01-05"] = "Pregătește un amestec anti-păduchi: 1 litru apă + 2 linguri ulei de neem + 1 lingură săpun lichid. Stropiți pomii fructiferi.";
this.tips["01-06"] = "Înfășoară trunchiurile pomilor tineri cu pânză de sac pentru a preveni crapăturile de la ger.";
this.tips["01-07"] = "Plantează usturoi în sere: distanță 15 cm între căței, adâncime 5 cm. Folosește compost ca strat de bază.";
this.tips["01-08"] = "Fă un plan de irigații: calculează necesarul de apă pe bază de suprafață cultivată și tipul solului.";
this.tips["01-09"] = "Curăță uneltele de tăiat cu oțet și piatră ponce. Unelte ascuțite previn răni infectate la plante.";
this.tips["01-10"] = "Semănază rucola în mini-sere interioare: temperatura optimă 15-18°C, lumina indirectă 6 ore/zi.";
this.tips["01-11"] = "Prepară alifie pentru răni la pomi: 1 parte ceară de albine + 2 părți ulei de măsline. Topeste la baie marie.";
this.tips["01-12"] = "Testează pH-ul solului cu var/orez: pune pământ în borcan cu apă și o lingură de orez. Dacă spumește, solul e acid.";
this.tips["01-13"] = "Plantează arpagic în ghivece pe pervaz. Folosește sol cu 30% nisip pentru drenaj.";
this.tips["01-14"] = "Construiește un cadru rece din rame vechi de geamuri pentru culturi timpurii de primăvară.";
this.tips["01-15"] = "Îndepărtează lichenii de pe pomii bătrâni cu perie de sârmă. Nu folosi cuțite pentru a nu răni scoarța.";
this.tips["01-16"] = "Semănază levănțica în căsuțe de ouă: pune 3 semințe/căsuță, acoperă cu folie până la germinare.";
this.tips["01-17"] = "Pregătește tăvițe pentru răsaduri: spală cu săpun de vase și clătește cu apă cu puțină bleană.";
this.tips["01-18"] = "Fă un spray anti-fungi din 1 litru apă + 3 căței de usturoi zdrobiți. Lasă 24h, strecoară și stropeste răsadurile.";
this.tips["01-19"] = "Înmulțește afine prin butași: taie lăstari de 15 cm, plantează în amestec turbă + nisip (2:1).";
this.tips["01-20"] = "Verifică bulbii de lalele depozitați: aruncă cei mucegăiți sau moi. Păstrează doar cei tari și uscați.";
this.tips["01-21"] = "Semănază praz pentru răsad: folosește sol profund de 20 cm. Plantează semințe la 1 cm adâncime.";
this.tips["01-22"] = "Construiește un sistem de captare a apei de ploaie din jgheaburi vechi. Folosește doze de 200L.";
this.tips["01-23"] = "Pregătește un amestec de sol pentru răsaduri: 1/3 compost + 1/3 nisip + 1/3 turbă. Sterilizează la 120°C 30 min.";
this.tips["01-24"] = "Plantează mentă în recipiente izolate (e invazivă). Folosește ghivece de plastic îngropate în sol.";
this.tips["01-25"] = "Semănază salvie în căsuțe: acoperă semințele cu 0,5 cm sol. Germinare la 21°C în 14-21 zile.";
this.tips["01-26"] = "Fă un îngrășământ lichid din compost: pune 1 kg compost în 5L apă, lasă 3 zile, amestecă zilnic. Filtratul se diluează 1:10.";
this.tips["01-27"] = "Verifică sămânța de ardei iute: pune semințe între șervețele umede. Dacă nu germinează în 10 zile, înlocuiește.";
this.tips["01-28"] = "Pregătește etichete pentru răsaduri din lemn de la cutii de fructe. Scrie cu marker permanent rezistent la apă.";
this.tips["01-29"] = "Plantează rozmarin prin butași: taie lăstari de 10 cm, îndepărtează frunzele de jos, plantează în nisip umed.";
this.tips["01-30"] = "Semănază telina în săculețe de celuloză. Folosește sol bogat în humus și păstrează umed.";
this.tips["01-31"] = "Pregătește un calendar lunar de grădinărit: semănat la lună crescătoare, tăieri la lună descrescătoare.";
this.tips["02-01"] = "Taie pomii fructiferi tineri în formă de vas: păstrează 3-5 ramuri principale la 45° față de trunchi pentru circulația optimă a aerului [1]. Dezinfectează foarfeca cu alcool după fiecare tăietură pentru a preveni răspândirea bolilor [2].";
this.tips["02-02"] = "Pregătește substrat pentru răsaduri: amestecă 40% turbă, 30% compost matur, 20% perlită și 10% nisip de râu [1]. Sterilizează amestecul la 80°C timp de 30 de minute în cuptor [2].";
this.tips["02-03"] = "Semănă basilic în căsuțe pe pervaz: temperatura optimă de germinare este 20-25°C [1]. Acoperă semințele cu 0,5cm de substrat și menține umiditatea constantă cu folie transparentă [2].";
this.tips["02-04"] = "Tratează pomii cu ulei de dormant: amestecă 200ml ulei horticol la 10L apă și stropește când temperatura e peste 5°C [1]. Acest tratament elimină ouăle de păduchi și acarieni [2].";
this.tips["02-05"] = "Plantează cartofii pentru germinare într-un loc luminos și răcoros (12-15°C) [1]. Așează tuberculi cu ochii în sus pentru a stimula dezvoltarea lăstarilor viguroși [2].";
this.tips["02-06"] = "Pregătește soluție cu ceapă pentru tratarea semințelor: fierbe 1kg ceapă în 2L apă, strecoară și lasă semințele 2 ore în soluție [1]. Această metodă naturală previne putregaiul semințelor [2].";
this.tips["02-07"] = "Verifică bulbii depozitați și îndepărtează cei cu semne de mucegai sau înmuiere [1]. Păstrează doar bulbii fermi și fără pete pentru plantarea de primăvară [2].";
this.tips["02-08"] = "Semănă ridichile în sera încălzită: folosește soiuri timpurii rezistente la frig [1]. Distanța între semințe să fie de 3-4cm pentru dezvoltarea optimă a rădăcinii [2].";
this.tips["02-09"] = "Pregătește cadre reci pentru grădinitul timpuriu: orientează-le spre sud și asigură ventilație reglabilă [1]. Temperatura interioară să nu depășească 25°C ziua [2].";
this.tips["02-10"] = "Taie și mulcește trandafirii: îndepărtează crengile moarte și bolnave la 1cm deasupra unui ochi sănătos [1]. Aplică compost în jurul bazei și acoperă cu paie [2].";
this.tips["02-11"] = "Semănă praz pentru răsaduri în tăvițe adânci de minimum 15cm [1]. Substrat ideal: 50% turbă, 30% compost, 20% nisip pentru drenaj excelent [2].";
this.tips["02-12"] = "Pregătește îngrășământ din cenușă: amestecă 1kg cenușă de lemn dur cu 5kg compost [1]. Acest amestec furnizează potasiu pentru fructificarea abundentă [2].";
this.tips["02-13"] = "Plantează usturoiul în ghivece pentru forțat: folosește căței mari și sănătoși [1]. Temperatura optimă de germinare este 4-10°C timp de 6 săptămâni [2].";
this.tips["02-14"] = "Semănă spanacul gigant de iarnă în sera neîncălzită [1]. Aceste soiuri rezistă la -10°C și oferă frunze crocante până în aprilie [2].";
this.tips["02-15"] = "Pregătește soluție nutritivă pentru răsaduri: diluează îngrășământul lichid complex la 25% din concentrația recomandată [1]. Aplică o dată pe săptămână pentru creștere uniformă [2].";
this.tips["02-16"] = "Verifică sistemul de drenaj al ghivecelor: asigură-te că orificiile nu sunt înfundate [1]. Drainage-ul deficient provoacă putregaiul rădăcinilor și moartea plantelor [2].";
this.tips["02-17"] = "Semănă telina pentru răsaduri în substrat fin tamisat [1]. Semințele sunt foarte mici și necesită lumină pentru germinare - nu le acoperi complet [2].";
this.tips["02-18"] = "Tratează uneltele de tăiat cu pasta abrazivă pentru ascuțire [1]. Unelte ascuțite fac tăieturi curate care se vindecă rapid și reduc riscul de infecții [2].";
this.tips["02-19"] = "Pregătește bancile de germinare cu rezistență electrică pentru menținerea temperaturii de 20-25°C [1]. Controlul precis al temperaturii măre rata de germinare cu 40% [2].";
this.tips["02-20"] = "Semănă salata în sere pentru prima recoltă de primăvară [1]. Alege soiuri rezistente la montare timpurie pentru rezultate optime [2].";
this.tips["02-21"] = "Aplică tratament foliar cu extract de alge marine pentru întărirea plantelor [1]. Microelementele din alge îmbunătățesc rezistența la stres și boli [2].";
this.tips["02-22"] = "Pregătește soluția pentru dezinfectarea semințelor cu permanganat de potasiu 0,1% [1]. Tratamentul de 20 minute elimină patogenii de pe suprafața semințelor [2].";
this.tips["02-23"] = "Verifică și ajustează pH-ul substratului pentru răsaduri la 6.0-6.8 [1]. Folosește turnesol sau pH-metru digital pentru măsurători precise [2].";
this.tips["02-24"] = "Semănă mărul pământului (topinambur) în ghivece pentru înmulțire [1]. Taie tuberculii în bucăți cu câte 2-3 ochi și lasă să se usuce 24 ore [2].";
this.tips["02-25"] = "Pregătește amestec de țărână pentru plantele acidofile: 40% turbă de sfagnum, 30% pământ de frunze, 30% nisip [1]. Acest substrat este ideal pentru afine și azalee [2].";
this.tips["02-26"] = "Tratează răsadurile cu soluție de vitamina B1 pentru stimularea sistemului radicular [1]. Concentrația optimă este 100mg/L aplicată la fiecare 2 săptămâni [2].";
this.tips["02-27"] = "Pregătește soluție antifungică din bicarbonat de sodiu: 5g la 1L apă plus o picătură detergent [1]. Previne eficient mana și făinarea la răsaduri [2].";
this.tips["02-28"] = "Planifică rotația culturilor pentru sezonul următor: evită plantarea aceluiași tip de legume în același loc 3 ani consecutiv [1]. Rotația previne epuizarea solului și acumularea dăunătorilor [2].";
this.tips["03-01"] = "Semănă roșiile pentru răsaduri în substrat sterilizat la temperatura de 22-25°C [1]. Folosește soiuri adaptate la climatul local pentru rezultate garantate [2].";
this.tips["03-02"] = "Pregătește cadrul rece pentru aclimatizarea răsadurilor: asigură ventilație graduală [1]. Temperatura interioară să scadă treptat pentru întărirea plantelor [2].";
this.tips["03-03"] = "Tratează solul cu gips agricol pentru îmbunătățirea structurii: 200g/m² pe soluri argiloase [1]. Gipsul ameliorează compactarea și îmbunătățește infiltrația apei [2].";
this.tips["03-04"] = "Semănă ardeii și patenele în mini-sere la temperatura constantă de 25°C [1]. Semințele de ardei necesită călzură constantă pentru germinare uniformă [2].";
this.tips["03-05"] = "Pregătește compostul rapid cu acceleratori naturali: adaugă bicarbonat de amoniu [1]. Procesul de compostare se reduce de la 6 luni la 3 luni [2].";
this.tips["03-06"] = "Plantează cartofii timpurii în solul protejat de folii negre pentru încălzire [3]. Folia neagră absoarbe radiația solară și încălzește solul cu 3-5°C [1].";
this.tips["03-07"] = "Semănă morcovii în rânduri distanțate la 25cm pentru ușurința întreținerii [1]. Amestecă semințele cu nisip fin pentru răspândire uniformă [2].";
this.tips["03-08"] = "Tratează arborii fructiferi cu suspensie de var pentru protecția împotriva dăunătorilor [1]. Aplicația pe scoarță reflectă radiația și previne fisurarea [2].";
this.tips["03-09"] = "Pregătește soluție nutritivă pentru hidroponie cu NPK 20-20-20 la 1g/L [1]. Monitorizează pH-ul soluției să rămână între 5.5-6.5 [2].";
this.tips["03-10"] = "Semănă fasolea păstăi în ghivece biodegradabile pentru transplantare fără stres [1]. Rădăcinile fasole sunt sensibile la deranjare [2].";
this.tips["03-11"] = "Verifică sămânța de fasole veche: înmoaie 10 boabe în apă 24h. Dacă mai puțin de 7 umflă, înlocuiește [1].";
this.tips["03-12"] = "Plantează liliacul în sol bine drenat: groapa de 50x50cm cu strat de 10cm pietriș la fund [1]. Adaugă 1kg compost + 200g superfosfat/groapă [2].";
this.tips["03-13"] = "Pregătește alifie pentru tăieturi la pomi: 1 parte ceară albine + 2 părți ulei de măsline [1]. Aplică pe rănile mai mari de 2cm [2].";
this.tips["03-14"] = "Semănă rucola în vase adânci (minimum 20cm): 5 semințe/ghiveci la 1cm adâncime [1]. Recoltă frunzele când ating 10cm [2].";
this.tips["03-15"] = "Tratează solul cu gips agricol pe terenuri argiloase: 200g/m² [1]. Îmbunătățește structura solului și infiltrația apei [2].";
this.tips["03-16"] = "Plantează cartofii timpurii sub folie neagră: distanță 30cm între tuberculi [1]. Folia crește temperatura solului cu 3-5°C [2].";
this.tips["03-17"] = "Semănă morcovii de primăvară în sol nisipos: adâncime 1cm, distanță între rânduri 25cm [1]. Alege soiuri rezistente la fisurare precum 'Nantes' [2].";
this.tips["03-18"] = "Construiește un sistem de captare a apelor pluviale din jgheaburi: 1m² acoperiș = 1L apă/1mm ploaie [1].";
this.tips["03-19"] = "Sărbătoarea Mărțișorului: plantează măceșii lângă pomii fructiferi pentru atragerea polenizatorilor [1].";
this.tips["03-20"] = "Echilibrează pH-ul solului cu puiet de pădure: 5kg/m² pentru soluri acide [1]. Testează cu kit de pH lunar [2].";
this.tips["03-21"] = "Semănă busuiocul sacru (Ocimum sanctum) în ghivece: temperatura minimă 15°C [1]. Folosește la ceaiuri medicinale [2].";
this.tips["03-22"] = "Pregătește stratificare la semințele de măr: pune în frigider la 4°C pentru 60 zile în nisip umed [1].";
this.tips["03-23"] = "Plantează zmeura în rânduri la 1.5m distanță [1]. Tăiește lăstarii la 30cm înălțime pentru înrădăcinare puternică [2].";
this.tips["03-24"] = "Semănă pătrunjelul în sol umed: 0.5cm adâncime, 10cm între rânduri [1]. Recoltează după 75-90 zile [2].";
this.tips["03-25"] = "Băltește răsadurile de roșii cu apă de ploaie: 20°C, o dată la 3 zile [1]. Evită udarea frunzelor [2].";
this.tips["03-26"] = "Pivestește răsadurile de vinete: 12°C noaptea timp de 5 zile pentru adaptare la exterior [1].";
this.tips["03-27"] = "Plantează salvie medicinală în zone însorite: pH 6.0-7.0 [1]. Taie tulpinile la 10cm înălțime pentru ramificare [2].";
this.tips["03-28"] = "Semănă ridichile de vară direct în sol: 2cm adâncime, 5cm între plante [1]. Recoltează în 25-30 zile [2].";
this.tips["03-29"] = "Aplică mușchi de turbă la baza trandafirilor: strat de 5cm pentru menținerea umidității [1].";
this.tips["03-30"] = "Instalează plase împotriva păsărilor la serele cu răsaduri [1]. Folosește ochiuri de 2cm pentru eficiență maximă [2].";
this.tips["03-31"] = "Verifică irigația prin picurare: presiune optimă 1.5-2.5 bar [1]. Curăță filtrele săptămânal [2].";
this.tips["04-01"] = "Plantează cartofii timpurii în solul încălzit peste 8°C: adâncime 10cm, distanță 30cm între tuberculi [1]. Acoperă cu folie neagră pentru protecție împotriva înghețului tardiv și încălzirea solului cu 3-5°C [7].";
this.tips["04-02"] = "Semănă mazărea în rânduri duble la 15cm distanță: suportă temperaturi până la -5°C [1]. Instalează plase de 1.8m înălțime pentru soiurile de cățărătoare [2].";
this.tips["04-03"] = "Pregătește soluție organică anti-păduchi: 200g săpun de Marsilia ras + 2L apă caldă [13]. Amestecă până se dizolvă și aplică cu pulverizator dimineața devreme [15].";
this.tips["04-04"] = "Plantează ceapa de iarnă în solul bine drenat: pH 6.0-7.0, distanță 10cm între bulbi [1]. Adaugă 150g cenușă de lemn/m² pentru potasiu și prevenirea putregaiului [3].";
this.tips["04-05"] = "Construiește paturi ridicate pentru seniori: înălțime 70-80cm, lățime maximă 120cm pentru accesibilitate [14]. Umple cu 40% compost, 30% sol vegetal, 30% nisip de râu [16].";
this.tips["04-06"] = "Transplantează răsadurile de roșii în sere neîncălzite când temperatura nocturnă depășește 10°C [1]. Distanță 50cm între plante pentru circulația aerului [2].";
this.tips["04-07"] = "Semănă morcovii de vară direct în sol: 1cm adâncime, amestecă semințele cu nisip fin pentru răspândire uniformă [1]. Distanță între rânduri 25cm [8].";
this.tips["04-08"] = "Aplică bordelez la pomii fructiferi: 300g var stins + 300g sulfat de cupru la 10L apă [2]. Tratează când temperatura este peste 8°C pentru prevenirea manei și rugginii [3].";
this.tips["04-09"] = "Plantează fasolea păstăi în ghivece biodegradabile pentru transplantare fără stres radicular [8]. Temperatura minimă de germinare: 12°C [1].";
this.tips["04-10"] = "Începe sezonul de plantare directă: porumb, dovleci, castraveți când solul atinge 15°C [1]. Verifică prognoza meteo pentru următoarele 10 zile fără îngheț [7].";
this.tips["04-11"] = "Instalează sistem de irigare prin picurare pentru legumele în sere: 2-4L apă/plantă/zi [3]. Programează udarea dimineața devreme pentru eficiență maximă [2].";
this.tips["04-12"] = "Semănă salata în succesiuni la interval de 2 săptămâni pentru recoltă continuă [8]. Alege soiuri rezistente la montare timpurie pentru rezultate optime [2].";
this.tips["04-13"] = "Pregătește amestec pentru mulciș organic: paie de grâu + rumeguș de lemn în părți egale [3]. Aplică strat de 7-10cm la baza roșiilor pentru reținerea umidității [2].";
this.tips["04-14"] = "Plantează busuiocul lângă roșii pentru respingerea țânțarilor și îmbunătățirea aromei [13]. Temperatura minimă de plantare: 15°C [8].";
this.tips["04-15"] = "Aplică îngrășământ organic la trandafiri: 2kg compost + 100g făină de oase/tufă [2]. Presară în jurul bazei și încorporează în primul strat de sol [3].";
this.tips["04-16"] = "Semănă ardeii iuți în ghivece cu substrat drenat: pH 6.0-6.8 [1]. Folosește soiuri locale adaptate climatului continental românesc [7].";
this.tips["04-17"] = "Construiește cadre reci portabile pentru protecția răsadurilor: orientare sud-est pentru lumina maximă [2]. Asigură ventilație reglabilă pentru zilele călduroase [3].";
this.tips["04-18"] = "Plantează căpșuni în paturi ridicate cu mulciș de paie: distanță 30cm între plante [2]. Alege soiuri remontante pentru recoltă până în octombrie [3].";
this.tips["04-19"] = "Tratează preventiv viața de vie cu soluție de bicarbonat: 5g/L apă pentru combaterea manei [15]. Aplică săptămânal până la înflorire [13].";
this.tips["04-20"] = "Semănă spanacul de vară în umbra parțială pentru evitarea montării rapide [8]. Ud cu apă de ploaie pentru menținerea prospețimii frunzelor [2].";
this.tips["04-21"] = "Instalează plase de protecție împotriva păsărilor la culturile de cereale [2]. Folosește ochiuri de 2cm pentru eficiență maximă fără a afecta polenizatorii [3].";
this.tips["04-22"] = "Pregătește sol pentru plantarea dovlecilor: groapa 50x50cm cu 3kg compost + 200g cenușă [1]. Acoperă cu folie neagră pentru încălzire [7].";
this.tips["04-23"] = "Sărbătoarea Sf. Gheorghe: plantează porumbul tradițional cu semințe moștente din anul trecut [9]. Îngroapă la 5cm adâncime în sol încălzit peste 12°C [1].";
this.tips["04-24"] = "Semănă castraveții pentru conserve direct în sol: distanță 1m între plante [1]. Construiește spaliere înalte de 2m pentru soiurile de cățărătoare [8].";
this.tips["04-25"] = "Aplică tratament foliar cu extract de urzici fermentate: diluție 1:10 [13]. Îmbunătățește rezistența plantelor la dăunători și boli fungice [15].";
this.tips["04-26"] = "Plantează ierburi aromatice în ghivece pe pervaz: rozmarin, cimbru, oregano [2]. Folosește substrat cu 30% nisip pentru drenaj excelent [16].";
this.tips["04-27"] = "Semănă floarea-soarelui în sol adânc și fertil: adâncime 3cm, distanță 50cm [2]. Protejează semințele de păsări cu plasă fină primele 2 săptămâni [3].";
this.tips["04-28"] = "Verifică și curăță sistemele de drenaj la ghivecele cu plante perene [16]. Înlocuiește stratul superior de substrat cu compost proaspăt [14].";
this.tips["04-29"] = "Plantează napi pentru conserve de iarnă: semințe la 1cm adâncime, recoltă în septembrie [8]. Alege soiuri cu rădăcini mari pentru depozitare îndelungată [1].";
this.tips["04-30"] = "Pregătește grădina pentru luna mai: verifică toate sistemele de irigare și repară scurgerile [3]. Planifică plantările succesive pentru recoltă continuă [2].";
this.tips["05-01"] = "Sărbătoarea Armindenului: plantează usturoiul românesc roșu (Allium sativum 'Romanian Red') în sol bine drenat, pH 6.5-7.0 [1]. Distanță 15cm între căței, adâncime 5cm cu vârful în sus [5].";
this.tips["05-02"] = "Pregătește soluție anti-păduchi: 2 linguri săpun de Marsilia + 1L apă caldă [1]. Aplică dimineața pe frunzele atacate, evitând orele de soare puternic [3].";
this.tips["05-03"] = "Plantează cartofii timpurii în zonele de deal (Zona 5-6): distanță 40cm între rânduri [2]. Acoperă cu paie pentru menținerea umidității [6].";
this.tips["05-04"] = "Semănă fasolea păstăi direct în sol: temperatura minimă 12°C [3]. Folosește grămezi de 3-4 semințe la 50cm distanță [1].";
this.tips["05-05"] = "Începe recoltarea rucolei sălbatice (Diplotaxis tenuifolia) pentru salate [7]. Taie doar 1/3 din frunze pentru regenerare rapidă [1].";
this.tips["05-06"] = "Plantează roșiile în sere neîncălzite: distanță 60cm, adaugă 200g coajă de ouă zdrobită/groapă pentru calciu [3].";
this.tips["05-07"] = "Construiește spaliere pentru castraveți din nuiele de salcie: înălțime 1.5m, distanță între suporturi 40cm [1].";
this.tips["05-08"] = "Semănă floarea-soarelui 'Gigantul de Ungheni': 3cm adâncime, 50cm între plante [2]. Protejează semințele cu plasă împotriva păsărilor [6].";
this.tips["05-09"] = "Aplică compost de urzici la trandafiri: 1L soluție diluată 1:10/plantă [1]. Îmbunătățește rezistența la mană și ruginiu [3].";
this.tips["05-10"] = "Plantează ardei iute 'Gorilla' în zonele calde (Zona 7-8): temperatură nocturnă minimă 15°C [2]. Folosește mulch din paie de grâu [5].";
this.tips["05-11"] = "Semănă salata de vară 'Lollo Rosso' în umbră parțială: distanță 25cm, udă la rădăcină dimineața [3].";
this.tips["05-12"] = "Pregătește ceai de pelin pentru combaterea furnicilor: 100g frunze uscate la 1L apă fiartă [1]. Stropiște pe traseele lor [4].";
this.tips["05-13"] = "Plantează busuioc sfânt (Ocimum sanctum) lângă uși și ferestre pentru protecție tradițională [4]. Temperatura minimă 10°C [5].";
this.tips["05-14"] = "Semănă morcovii 'Nantes' în sol nisipos: adâncime 1cm, distanță 5cm între semințe [3]. Subțiază la 10cm după 3 săptămâni [6].";
this.tips["05-15"] = "Pivestește răsadurile de vinete: 12°C noaptea timp de 5 zile înainte de plantare [1]. Folosește compost matur în gropi [2].";
this.tips["05-16"] = "Plantează liliacul românesc (Syringa vulgaris) în sol calcaros: groapă 60x60cm cu 2kg compost [7].";
this.tips["05-17"] = "Semănă mărarul pentru conserve: distanță 20cm între rânduri, recoltă la 60cm înălțime [1]. Usucă în mănunchiuri cu capul în jos [4].";
this.tips["05-18"] = "Aplică tratament cu lapte împotriva manei la roșii: 1 parte lapte la 9 părți apă [3]. Stropiți săptămânal dimineața [1].";
this.tips["05-19"] = "Plantează arpagicul în ghivece pe pervaz: substrat 30% nisip, udă când solul e uscat la atingere [6].";
this.tips["05-20"] = "Semănă floarea-soarelui 'Titan' pentru semințe mari: temperatură sol 15°C, adâncime 4cm [5]. Distanță 70cm între plante [2].";
this.tips["05-21"] = "Construiește un sistem de udare cu sticle reciclate: umple sticlele de 2L și îngroapă lângă plante cu găurile spre rădăcini [1].";
this.tips["05-22"] = "Plantează salvie medicinală (Salvia officinalis) lângă ferestre: protejează împotriva țânțarilor [4]. Taie tulpinile la 10cm [3].";
this.tips["05-23"] = "Semănă castraveții 'Dăbuleni' direct în sol: temperatură minimă 18°C noaptea [2]. Folosește mulch din paie pentru menținerea umidității [6].";
this.tips["05-24"] = "Pregătește alifie pentru tăieturi la pomi: 1 parte ceară de albine + 2 părți ulei de măsline [1]. Aplică pe rănile mai mari de 2cm [3].";
this.tips["05-25"] = "Plantează zmeura 'Heritage' în rânduri de 1.5m [2]. Tăiește lăstarii la 30cm pentru înrădăcinare puternică [6].";
this.tips["05-26"] = "Semănă ridichile de vară 'Saxa' în umbră parțială: recoltă în 25 zile [3]. Amestecă semințele cu nisip pentru răspândire uniformă [1].";
this.tips["05-27"] = "Aplică îngrășământ din banane fermentate: 3 bucăți la 10L apă timp de 3 zile [1]. Bogat în potasiu pentru înflorire abundentă [5].";
this.tips["05-28"] = "Plantează menta 'Piperita' în ghivece izolate: substrat 40% nisip [1]. Taie tulpinile la 15cm pentru frunze mai mari [3].";
this.tips["05-29"] = "Semănă porumbul dulce 'Târnava' în grămezi de 4-5 semințe: distanță 80cm între grămezi [2]. Temperatura sol minim 12°C [6].";
this.tips["05-30"] = "Construiește un hotel pentru albine singuratice din trestie de râu: atașează la 1m înălțime, orientare sud-est [7].";
this.tips["05-31"] = "Pregătește ghivece pentru iarnă: sterilizează cu alcool 70% și usucă la soare [1]. Adaugă strat drenant de 5cm pietriș [3].";
this.tips["06-01"] = "Sărbătoarea Învierii Domnului (Rusalii): plantează basilic sfânt pentru protecția casei. Folosește substrat bogat în humus cu pH 6.0-7.0. Temperatura minimă de plantare: 18°C.";
this.tips["06-02"] = "Leagă roșiile de țăruși cu panglici moi de material textil: evită sârma care poate tăia tulpinile. Înălțime țăruși: 1.8m pentru soiurile nedeterminate.";
this.tips["06-03"] = "Semănă fasolea boabe 'Alb de Ploiești' pentru conserve de iarnă: adâncime 4cm, distanță 15cm. Recoltă când păstăile sunt uscate pe plantă.";
this.tips["06-04"] = "Pregătește soluție anti-păduchi din usturoi: 5 căței zdrobiți în 1L apă, lasă 24h. Stropiți dimineața devreme pentru eficiență maximă.";
this.tips["06-05"] = "Recoltează primul spanac înainte de montare: taie frunzele exterioare la 2cm de sol. Plantele regenerează în 2-3 săptămâni pentru a doua recoltă.";
this.tips["06-06"] = "Plantează castraveții 'Cornichon de Paris' pentru murături: distanță 50cm, spaliere înalte de 1.5m. Udă zilnic cu 2L apă/plantă.";
this.tips["06-07"] = "Construiește umbrar pentru salata de vară din plasă de 50% opacitate. Orientează sud-vest pentru protecția în orele de după-amiază.";
this.tips["06-08"] = "Semănă morcovii 'Chantenay' pentru iarnă: sol adânc de 25cm, fără pietre. Recoltă în octombrie pentru depozitare.";
this.tips["06-09"] = "Aplică mulch din paie la baza roșiilor: strat de 8cm pentru menținerea umidității constante. Evită contactul cu tulpina pentru prevenirea putregaiului.";
this.tips["06-10"] = "Începe recoltarea ridichilor 'Cherry Belle': diametru optimal 2-3cm. Consumă în maxim 3 zile pentru prospețime.";
this.tips["06-11"] = "Plantează cuișoarele (Dianthus caryophyllus) pentru parfumul intens. Sol calcaros, pH 7.0-8.0, drenaj excelent.";
this.tips["06-12"] = "Semănă dovleacul 'Muscat de Provence' pentru Halloween: gropi 50x50cm cu 3kg compost. Distanță 2m între plante.";
this.tips["06-13"] = "Pregătește ceai de cozi de ceapă pentru întărirea plantelor: 100g cozi la 1L apă fiartă. Lasă să se răcească și udă cu soluția diluată 1:5.";
this.tips["06-14"] = "Îndepărtează lăstarii de la roșii săptămânal: rupture cu degetele la 5cm lungime. Operația se face dimineața când plantele sunt hidratate.";
this.tips["06-15"] = "Recoltează cireșele timpurii 'Cătălina': în zori când sunt răcoroase pentru păstrare îndelungată. Folosește coș căptușit cu foi de cireș.";
this.tips["06-16"] = "Semănă salata 'Ice Berg' pentru vara târzie: în umbră parțială, udă cu apă rece. Substratul să fie mereu umed dar nu ud.";
this.tips["06-17"] = "Plantează lavanda 'Hidcote Blue' pe marginile aleilor: distanță 40cm, sol nisipos. Taie tulpinile după înflorire pentru formă compactă.";
this.tips["06-18"] = "Construiește capcane pentru limacsi din bere românească în pahare îngropate. Înlocuiește berea la 3 zile pentru eficiență.";
this.tips["06-19"] = "Semănă varza de iarnă 'Brăila' în răsadniță: substrat sterilizat, temperatura 16-18°C. Transplantează în august.";
this.tips["06-20"] = "Solstițiul de vară: recoltează plante medicinale la puterea maximă. Mușețelul, mentă și salvie se usucă în mănunchiuri.";
this.tips["06-21"] = "Udă grădina seara târziu (după ora 20) pentru evaporare minimă. Folosește aspersoare cu picături mari pentru penetrare profundă.";
this.tips["06-22"] = "Plantează porumbul dulce 'Golden Bantam' pentru toamnă: ultimă șansă în zonele calde. Protejează cu plasă împotriva păsărilor.";
this.tips["06-23"] = "Pregătește extract de pelin pentru combaterea moliilor: 300g frunze proaspete la 3L apă. Fierbe 20 minute, strecoară și stropiți seara.";
this.tips["06-24"] = "Sărbătoarea Sânzienelor: culege ierburi magice (sunătoare, coada șoricelului) în această noapte. Usucă pentru ceaiuri de iarnă.";
this.tips["06-25"] = "Recoltează castraveții tineri pentru murături: lungime 5-7cm, culoare verde intens. Culeagerea zilnică stimulează producția.";
this.tips["06-26"] = "Semănă napi pentru conserve 'Purple Top': sol profund, udare abundentă. Recoltă în septembrie când au 8-10cm diametru.";
this.tips["06-27"] = "Aplică îngrășământ din compost de alge marine la roșii: 200g/plantă. Bogat în microelemente pentru fructe aromate.";
this.tips["06-28"] = "Construiește sistem de umbrar mobil din țăruși și pânză. Protejează răsadurile în orele 12-16 când radiația e intensă.";
this.tips["06-29"] = "Plantează celina pentru rădăcini mari: sol adânc de 30cm, bogat în humus. Distanță 30cm între plante pentru dezvoltare optimă.";
this.tips["06-30"] = "Pregătește grădina pentru iulie: verifică irigația, repară mulchiul și planifică a doua recoltă. Comandă semințe pentru culturile de toamnă.";
this.tips["07-01"] = "Recoltează usturoiul când frunzele de jos se îngălbenesc. Scoate-l cu furca, lasă-l să se usuce la umbră și aer curat timp de 2 săptămâni, apoi curăță și păstrează-l în loc răcoros.";
this.tips["07-02"] = "Udă roșiile la rădăcină, nu pe frunze, pentru a preveni mana. Udarea se face dimineața devreme, folosind 2-3 litri de apă/plantă la fiecare 3 zile.";
this.tips["07-03"] = "Aplică paie sau frunze uscate ca mulci sub ardei și vinete. Mulciul păstrează solul răcoros, reduce buruienile și menține umiditatea.";
this.tips["07-04"] = "Îndepărtează lăstarii laterali (copili) de la roșiile nedeterminate. Rupe-i cu mâna, dimineața, când sunt fragede.";
this.tips["07-05"] = "Verifică zilnic castraveții pentru fructe gata de cules. Culege-i la 10-12 cm lungime pentru a stimula producția continuă.";
this.tips["07-06"] = "Aplică tratament cu lapte (1 parte lapte la 9 părți apă) pe frunzele de dovlecei și castraveți pentru prevenirea făinării.";
this.tips["07-07"] = "Recoltează ceapa când frunzele încep să se culce. Scoate bulbii, usucă-i 2 săptămâni la umbră, apoi curăță și depozitează în plase aerisite.";
this.tips["07-08"] = "Plantează varza de toamnă în grădină. Folosește răsaduri viguroase, distanță de 50cm între plante și 60cm între rânduri.";
this.tips["07-09"] = "Verifică pomii fructiferi pentru fructe bolnave sau putrezite. Îndepărtează-le imediat pentru a preveni răspândirea bolilor.";
this.tips["07-10"] = "Udă salata și spanacul dimineața devreme. În zilele caniculare, protejează-le cu un umbrar improvizat din pânză albă.";
this.tips["07-11"] = "Taie lăstarii de zmeur care au rodit, la nivelul solului, pentru a stimula creșterea lăstarilor noi și recolta de anul viitor.";
this.tips["07-12"] = "Aplică îngrășământ lichid din compost la roșii și ardei. Diluează 1 litru de compost lichid în 10 litri de apă și udă la rădăcină.";
this.tips["07-13"] = "Verifică plantele de cartof pentru gândacul de Colorado. Adună manual adulții și larvele, sau folosește ceai de pelin ca tratament natural.";
this.tips["07-14"] = "Culege plantele aromatice (busuioc, mentă, oregano) dimineața, înainte de a înflori, pentru a păstra aroma intensă la uscare.";
this.tips["07-15"] = "Plantează fasolea verde pentru recolta de toamnă. Alege soiuri cu maturitate rapidă și udă regulat pentru germinare bună.";
this.tips["07-16"] = "Îndepărtează frunzele inferioare bolnave de la roșii și ardei. Ajută la circulația aerului și previne răspândirea bolilor.";
this.tips["07-17"] = "Aplică mulci de iarbă tăiată sub dovleci și pepeni pentru a păstra solul umed și a preveni contactul fructelor cu pământul.";
this.tips["07-18"] = "Verifică sistemul de irigație. Curăță filtrele și duzele pentru a asigura o udare uniformă.";
this.tips["07-19"] = "Recoltează dovleceii când au 15-20cm lungime. Culegerea timpurie stimulează apariția altor fructe.";
this.tips["07-20"] = "Plantează ridichi de toamnă și sfeclă roșie pentru recoltă la sfârșit de septembrie. Udă constant pentru rădăcini crocante.";
this.tips["07-21"] = "Verifică tufele de coacăze și agrișe pentru fructe coapte. Culege-le dimineața, când sunt răcoroase, pentru păstrare mai bună.";
this.tips["07-22"] = "Taie vârfurile lăstarilor de castraveți pentru a stimula ramificarea și producția de fructe.";
this.tips["07-23"] = "Aplică tratament cu macerat de urzici (1:10) la roșii și vinete pentru a preveni carențele de azot.";
this.tips["07-24"] = "Verifică trandafirii pentru păduchi verzi. Îndepărtează-i manual sau stropește cu apă cu săpun natural.";
this.tips["07-25"] = "Recoltează usturoiul de toamnă. Lasă-l la uscat în șiraguri, la umbră, pentru păstrare pe termen lung.";
this.tips["07-26"] = "Plantează prazul pentru recoltă de toamnă. Folosește răsaduri sănătoase, distanță 15cm între plante.";
this.tips["07-27"] = "Udă pepenii verzi și galbeni la rădăcină, dimineața devreme. Evită udarea frunzelor pentru a preveni bolile fungice.";
this.tips["07-28"] = "Îndepărtează lăstarii de la vița de vie care nu poartă ciorchini. Ajută la maturarea strugurilor și la aerisirea butucului.";
this.tips["07-29"] = "Culege semințe de salată și ridichi pentru anul următor. Uscă-le bine și păstrează-le în pungi de hârtie la răcoare.";
this.tips["07-30"] = "Aplică tratament cu zeamă bordeleză (sulfat de cupru + var stins) la roșii și cartofi pentru prevenirea manei.";
this.tips["07-31"] = "Pregătește grădina pentru culturile de toamnă: sapă adânc, adaugă compost proaspăt și planifică rotația culturilor.";
this.tips["08-01"] = "Recoltează roșiile coapte dimineața devreme pentru aroma maximă. Nu păstra roșiile la frigider, ci într-un loc răcoros și aerisit.";
this.tips["08-02"] = "Verifică plantele de dovlecei și castraveți pentru fructe ascunse. Culegerea la timp stimulează producția continuă.";
this.tips["08-03"] = "Aplică mulci proaspăt sub ardei și vinete pentru a păstra solul răcoros în zilele caniculare.";
this.tips["08-04"] = "Plantează varza de toamnă și broccoli pentru recoltă în octombrie. Folosește răsaduri sănătoase și udă constant.";
this.tips["08-05"] = "Udă grădina dimineața devreme sau seara târziu pentru a reduce evaporarea apei și stresul termic la plante.";
this.tips["08-06"] = "Verifică pomii fructiferi pentru fructe căzute sau bolnave. Îndepărtează-le pentru a preveni răspândirea bolilor.";
this.tips["08-07"] = "Plantează spanac pentru cultură de toamnă. Semănă direct în sol, în rânduri la 20cm distanță.";
this.tips["08-08"] = "Aplică tratament cu macerat de coada-calului (1:5) la roșii și castraveți pentru prevenirea bolilor fungice.";
this.tips["08-09"] = "Recoltează ceapa și usturoiul de toamnă. Lasă-le la uscat în aer liber, la umbră, timp de 2 săptămâni.";
this.tips["08-10"] = "Îndepărtează frunzele bolnave sau îngălbenite de la plantele de tomate și ardei pentru a preveni răspândirea bolilor.";
this.tips["08-11"] = "Semănă ridichi de toamnă pentru recoltă în septembrie-octombrie. Udă constant pentru rădăcini crocante.";
this.tips["08-12"] = "Verifică plantele de fasole pentru păstăi uscate. Culege-le și păstrează semințele pentru anul viitor.";
this.tips["08-13"] = "Aplică tratament cu lapte (1:9 cu apă) pe frunzele de dovlecei pentru prevenirea făinării.";
this.tips["08-14"] = "Plantează salată de toamnă și andive pentru recoltă târzie. Semănă în locuri semiumbrite.";
this.tips["08-15"] = "Sărbătoarea Adormirii Maicii Domnului: plantează usturoi pentru recoltă timpurie anul viitor.";
this.tips["08-16"] = "Verifică sistemul de irigare și curăță filtrele pentru a preveni blocajele.";
this.tips["08-17"] = "Recoltează plantele aromatice (busuioc, mentă, cimbru) pentru uscare. Leagă-le în buchete și atârnă-le la umbră.";
this.tips["08-18"] = "Plantează praz pentru recoltă de toamnă târzie. Folosește răsaduri viguroase și udă regulat.";
this.tips["08-19"] = "Aplică mulci de paie sub pepeni și dovleci pentru a preveni contactul direct cu solul și apariția putregaiului.";
this.tips["08-20"] = "Verifică plantele de cartof pentru gândacul de Colorado. Adună manual adulții și larvele.";
this.tips["08-21"] = "Udă răsadurile de varză și broccoli regulat pentru a preveni stresul hidric și apariția gustului amar.";
this.tips["08-22"] = "Plantează spanac și salată pentru cultură de toamnă. Semănă în sol umed și fertil.";
this.tips["08-23"] = "Aplică tratament cu zeamă bordeleză la vița de vie pentru prevenirea manei.";
this.tips["08-24"] = "Recoltează ardeii grași când sunt bine colorați și tari la atingere. Culegerea la timp stimulează apariția altor fructe.";
this.tips["08-25"] = "Îndepărtează frunzele inferioare de la roșii pentru a îmbunătăți circulația aerului și a preveni bolile.";
this.tips["08-26"] = "Semănă morcovi de toamnă pentru recoltă târzie. Alege soiuri cu maturitate rapidă.";
this.tips["08-27"] = "Verifică trandafirii pentru păduchi verzi. Îndepărtează-i manual sau folosește apă cu săpun natural.";
this.tips["08-28"] = "Aplică îngrășământ lichid din compost la ardei și vinete. Udă la rădăcină, evitând frunzele.";
this.tips["08-29"] = "Recoltează semințe de dovleac pentru anul viitor. Spală-le, usucă-le și păstrează-le la răcoare.";
this.tips["08-30"] = "Plantează varză de Bruxelles pentru recoltă târzie. Alege un loc însorit și sol bogat în humus.";
this.tips["08-31"] = "Pregătește grădina pentru toamnă: adaugă compost proaspăt, sapă adânc și planifică rotația culturilor.";
this.tips["09-01"] = "Recoltează ceapa și usturoiul de toamnă. Lasă-le la uscat în aer liber, la umbră, timp de 2 săptămâni pentru păstrare pe termen lung.";
this.tips["09-02"] = "Plantează spanac și salată pentru cultură de toamnă. Semănă direct în sol, în rânduri la 20cm distanță, și udă constant.";
this.tips["09-03"] = "Aplică compost proaspăt pe straturi goale pentru a îmbogăți solul înainte de iarnă.";
this.tips["09-04"] = "Verifică pomii fructiferi pentru fructe bolnave sau căzute. Îndepărtează-le pentru a preveni răspândirea bolilor.";
this.tips["09-05"] = "Plantează usturoi pentru recoltă timpurie anul viitor. Alege căței mari, sănătoși, și plantează-i la 5cm adâncime.";
this.tips["09-06"] = "Udă răsadurile de varză și broccoli regulat pentru a preveni stresul hidric și apariția gustului amar.";
this.tips["09-07"] = "Recoltează semințele de fasole, mazăre și dovleac pentru anul viitor. Uscă-le bine și păstrează-le la răcoare.";
this.tips["09-08"] = "Sărbătoarea Nașterii Maicii Domnului: plantează narcise și lalele pentru flori timpurii în primăvară.";
this.tips["09-09"] = "Aplică tratament cu zeamă bordeleză la vița de vie pentru prevenirea manei.";
this.tips["09-10"] = "Plantează ridichi de toamnă pentru recoltă în octombrie. Udă constant pentru rădăcini crocante.";
this.tips["09-11"] = "Verifică plantele de tomate și ardei pentru fructe bolnave sau putrezite. Îndepărtează-le imediat.";
this.tips["09-12"] = "Aplică mulci proaspăt pe straturile de legume pentru a menține umiditatea și a preveni buruienile.";
this.tips["09-13"] = "Recoltează merele și perele când sunt tari și au culoarea specifică soiului. Depozitează-le în lăzi aerisite la răcoare.";
this.tips["09-14"] = "Plantează varză de Bruxelles pentru recoltă târzie. Alege un loc însorit și sol bogat în humus.";
this.tips["09-15"] = "Verifică sistemul de irigare și curăță filtrele pentru a preveni blocajele.";
this.tips["09-16"] = "Plantează spanac și salată pentru cultură de toamnă târzie. Semănă în sol umed și fertil.";
this.tips["09-17"] = "Aplică tratament cu macerat de urzici (1:10) la legume pentru a preveni carențele de azot.";
this.tips["09-18"] = "Recoltează plantele aromatice (busuioc, mentă, cimbru) pentru uscare. Leagă-le în buchete și atârnă-le la umbră.";
this.tips["09-19"] = "Plantează usturoi și ceapă de toamnă pentru recoltă timpurie anul viitor.";
this.tips["09-20"] = "Udă răsadurile de varză și broccoli regulat pentru a preveni stresul hidric și apariția gustului amar.";
this.tips["09-21"] = "Aplică îngrășământ lichid din compost la ardei și vinete. Udă la rădăcină, evitând frunzele.";
this.tips["09-22"] = "Recoltează semințe de morcov și pătrunjel pentru anul viitor. Uscă-le bine și păstrează-le la răcoare.";
this.tips["09-23"] = "Plantează narcise și lalele pentru flori timpurii în primăvară. Alege bulbi sănătoși și plantează-i la 10cm adâncime.";
this.tips["09-24"] = "Verifică plantele de cartof pentru gândacul de Colorado. Adună manual adulții și larvele.";
this.tips["09-25"] = "Aplică tratament cu lapte (1:9 cu apă) pe frunzele de dovlecei pentru prevenirea făinării.";
this.tips["09-26"] = "Plantează praz pentru recoltă de toamnă târzie. Folosește răsaduri viguroase și udă regulat.";
this.tips["09-27"] = "Recoltează ardeii grași când sunt bine colorați și tari la atingere. Culegerea la timp stimulează apariția altor fructe.";
this.tips["09-28"] = "Aplică mulci de paie sub pepeni și dovleci pentru a preveni contactul direct cu solul și apariția putregaiului.";
this.tips["09-29"] = "Pregătește grădina pentru iarnă: adaugă compost proaspăt, sapă adânc și planifică rotația culturilor.";
this.tips["09-30"] = "Verifică și curăță sistemele de drenaj la ghivecele cu plante perene. Înlocuiește stratul superior de substrat cu compost proaspăt.";
this.tips["10-01"] = "Plantează usturoiul pentru recolta de anul viitor. Folosește căței mari, sănătoși, la 5-7cm adâncime și 10-15cm distanță între ei.";
this.tips["10-02"] = "Recoltează ultimele roșii și ardei înainte de primul îngheț. Pune fructele verzi la coacere în casă, la lumină indirectă.";
this.tips["10-03"] = "Adună frunzele căzute și folosește-le ca mulci sau pentru compost. Nu arde frunzele – compostul îmbogățește solul.";
this.tips["10-04"] = "Plantează ceapa și șalota de toamnă. Bulbii vor prinde rădăcini înainte de iarnă și vor da recolte timpurii în primăvară.";
this.tips["10-05"] = "Curăță și taie plantele anuale uscate (busuioc, mărar, fasole). Resturile sănătoase pot merge la compost.";
this.tips["10-06"] = "Plantează narcise, lalele, zambile și alte bulboase pentru flori timpurii în primăvară. Adâncime: 2-3 ori înălțimea bulbului.";
this.tips["10-07"] = "Aplică un strat gros de mulci (paie, frunze, compost) pe straturile goale pentru a proteja solul de îngheț și eroziune.";
this.tips["10-08"] = "Împarte și replantează tufele de perene (crini, stânjenei, bujori). Folosește unelte curate și plantează în sol fertil.";
this.tips["10-09"] = "Recoltează dovlecii și depozitează-i într-un loc uscat și răcoros. Lasă tulpina de 5cm pentru o păstrare mai bună.";
this.tips["10-10"] = "Verifică și repară sistemul de irigație. Golește furtunurile și depozitează-le la adăpost pentru a evita înghețarea.";
this.tips["10-11"] = "Plantează spanac și salată de iarnă în solar sau sub folie. Acestea rezistă la frig și pot fi recoltate până la primăvară.";
this.tips["10-12"] = "Taie ramurile uscate sau bolnave de la pomii fructiferi. Arde sau compostă resturile pentru a preveni bolile.";
this.tips["10-13"] = "Pregătește locul pentru pomi fructiferi noi: sapă gropi de 50x50cm, adaugă compost și lasă-le să se așeze până la plantare.";
this.tips["10-14"] = "Învelește trandafirii cu pământ și paie la bază pentru a-i proteja de ger. Poți folosi și frunze uscate sau compost.";
this.tips["10-15"] = "Plantează varză de Bruxelles și kale pentru recoltă târzie. Acestea rezistă la frig și devin mai dulci după brumă.";
this.tips["10-16"] = "Verifică depozitele de cartofi și rădăcinoase. Îndepărtează orice tubercul cu semne de putrezire pentru a proteja restul recoltei.";
this.tips["10-17"] = "Adaugă compost proaspăt pe straturile goale pentru a îmbogăți solul peste iarnă.";
this.tips["10-18"] = "Plantează panseluțe și crizanteme pentru culoare în grădină toată toamna și chiar iarna, dacă nu e ger puternic.";
this.tips["10-19"] = "Verifică și curăță uneltele de grădină. Ascuțirea și ungerea lor le prelungește viața și ușurează munca la primăvară.";
this.tips["10-20"] = "Plantează răsaduri de salată și spanac în ghivece mari pentru recoltă pe balcon sau terasă.";
this.tips["10-21"] = "Protejează plantele sensibile (leandru, dafin, mușcate) mutându-le în interior sau în spații ferite de îngheț.";
this.tips["10-22"] = "Aplică zeamă bordeleză la vița de vie și pomi pentru prevenirea bolilor fungice peste iarnă.";
this.tips["10-23"] = "Recoltează semințe de flori și legume pentru anul viitor. Uscă-le bine și păstrează-le la răcoare, în pungi de hârtie.";
this.tips["10-24"] = "Taie lăstarii de zmeur care au rodit, la nivelul solului, pentru a stimula creșterea lăstarilor noi.";
this.tips["10-25"] = "Verifică gardurile și suporturile pentru plante cățărătoare. Repară-le înainte de iarnă pentru a evita pagubele.";
this.tips["10-26"] = "Aplică un strat de compost sau gunoi de grajd bine descompus la baza pomilor și arbuștilor fructiferi.";
this.tips["10-27"] = "Plantează mazăre și bob pentru recoltă timpurie la primăvară. Acoperă semințele cu un strat de frunze pentru protecție.";
this.tips["10-28"] = "Recoltează ultimele vinete și dovlecei. Plantele pot fi scoase și compostate după recoltare.";
this.tips["10-29"] = "Pregătește compostul pentru iarnă: întoarce grămada și acoperă cu folie sau paie pentru a păstra căldura.";
this.tips["10-30"] = "Plantează arpagic și usturoi de toamnă în zonele cu ierni blânde. Acoperă cu mulci pentru protecție.";
this.tips["10-31"] = "Notează ce a mers bine și ce nu în grădina din acest an. Planificarea din timp ajută la un sezon viitor mai productiv.";
this.tips["11-01"] = "Plantează bulbi de lalele, narcise și zambile pentru flori timpurii în primăvară. Asigură o adâncime de plantare de 2-3 ori înălțimea bulbului.";
this.tips["11-02"] = "Curăță grădina de resturi vegetale și frunze bolnave. Compostează doar resturile sănătoase pentru a evita răspândirea bolilor.";
this.tips["11-03"] = "Aplică un strat gros de mulci (paie, frunze, compost) pe straturile goale pentru a proteja solul de îngheț și eroziune.";
this.tips["11-04"] = "Verifică depozitele de cartofi, ceapă și rădăcinoase. Îndepărtează orice tubercul cu semne de putrezire.";
this.tips["11-05"] = "Plantează usturoi și ceapă de toamnă în zonele cu ierni blânde. Acoperă cu mulci pentru protecție suplimentară.";
this.tips["11-06"] = "Mută plantele sensibile (mușcate, leandru, dafin) în interior sau în spații ferite de îngheț.";
this.tips["11-07"] = "Verifică și repară gardurile și suporturile pentru plante cățărătoare înainte de iarnă.";
this.tips["11-08"] = "Aplică zeamă bordeleză la vița de vie și pomi pentru prevenirea bolilor fungice peste iarnă.";
this.tips["11-09"] = "Recoltează ultimele mere și pere. Depozitează-le în lăzi aerisite, la răcoare, pentru păstrare îndelungată.";
this.tips["11-10"] = "Împarte și replantează tufele de perene (crini, bujori, stânjenei) pentru întinerirea și înmulțirea lor.";
this.tips["11-11"] = "Verifică și curăță uneltele de grădină. Ascuțirea și ungerea lor previne ruginirea și le prelungește viața.";
this.tips["11-12"] = "Acoperă straturile de căpșuni cu paie sau frunze uscate pentru protecție împotriva gerului.";
this.tips["11-13"] = "Plantează arpagic și usturoi de toamnă dacă nu ai făcut-o încă. Alege căței mari și sănătoși.";
this.tips["11-14"] = "Aplică compost proaspăt pe straturile goale pentru a îmbogăți solul peste iarnă.";
this.tips["11-15"] = "Recoltează și usucă plantele aromatice rămase (mentă, busuioc, cimbru) pentru ceaiuri și condimente de iarnă.";
this.tips["11-16"] = "Verifică și curăță sistemele de drenaj la ghivecele cu plante perene.";
this.tips["11-17"] = "Taie ramurile uscate sau bolnave de la pomii fructiferi. Arde sau compostă resturile pentru a preveni bolile.";
this.tips["11-18"] = "Aplică un strat de compost sau gunoi de grajd bine descompus la baza pomilor și arbuștilor fructiferi.";
this.tips["11-19"] = "Protejează trandafirii cu pământ și paie la bază pentru a-i feri de ger.";
this.tips["11-20"] = "Plantează panseluțe și crizanteme pentru culoare în grădină toată toamna și chiar iarna, dacă nu e ger puternic.";
this.tips["11-21"] = "Notează ce a mers bine și ce nu în grădina din acest an. Planificarea din timp ajută la un sezon viitor mai productiv.";
this.tips["11-22"] = "Curăță și depozitează furtunurile de irigație pentru a evita înghețarea și deteriorarea lor.";
this.tips["11-23"] = "Verifică depozitele de semințe. Uscă-le bine și păstrează-le la răcoare, în pungi de hârtie.";
this.tips["11-24"] = "Aplică zeamă bordeleză la pomii fructiferi pentru protecție împotriva bolilor fungice peste iarnă.";
this.tips["11-25"] = "Întoarce compostul pentru a accelera descompunerea și a obține un îngrășământ bogat la primăvară.";
this.tips["11-26"] = "Verifică și curăță adăposturile pentru unelte și materiale de grădinărit.";
this.tips["11-27"] = "Adună și depozitează frunzele uscate pentru mulci sau compost la primăvară.";
this.tips["11-28"] = "Plantează bulbi de flori de toamnă (lalele, narcise, zambile) dacă vremea permite.";
this.tips["11-29"] = "Protejează plantele sensibile de pe balcon sau terasă cu folie sau material textil special pentru iarnă.";
this.tips["11-30"] = "Odihnește-te, bucură-te de recolta strânsă și pregătește planurile pentru grădina de anul viitor.";
this.tips["12-01"] = "Protejează ghivecele și plantele sensibile de îngheț: mută-le la adăpost sau învelește-le cu folie sau pături groase. Rădăcinile în ghiveci sunt mai expuse frigului decât cele din pământ.";
this.tips["12-02"] = "Răzuiește și adună frunzele căzute de pe gazon și straturi. Folosește-le pentru a face compost sau leaf-mould, un îngrășământ excelent pentru anul viitor.";
this.tips["12-03"] = "Verifică depozitele de cartofi, ceapă și rădăcinoase. Îndepărtează orice tubercul cu semne de putrezire pentru a proteja restul recoltei.";
this.tips["12-04"] = "Redu udarea la plantele de interior și la cele iernate în seră. Umezeala excesivă favorizează apariția mucegaiului și a bolilor.";
this.tips["12-05"] = "Curăță și ascuțeste uneltele de grădină. Depozitează-le într-un loc uscat, ferit de îngheț, pentru a le păstra în stare bună.";
this.tips["12-06"] = "Plantează pomi fructiferi și arbuști ornamentali cu rădăcină nudă, cât timp solul nu este înghețat. Adaugă compost în groapă pentru pornire viguroasă.";
this.tips["12-07"] = "Prunează trandafirii cățărători și pomii fructiferi (măr, păr) cât timp sunt în repaus vegetativ. Îndepărtează ramurile bolnave sau uscate.";
this.tips["12-08"] = "Verifică și curăță adăposturile pentru păsări și umple hrănitoarele. Păsările ajută la controlul dăunătorilor prin consumul larvelor ascunse în scoarță.";
this.tips["12-09"] = "Plantează bulbi de lalele și narcise dacă vremea permite. O plantare târzie poate aduce flori mai târziu, dar tot vor înflori.";
this.tips["12-10"] = "Folosește paie, frunze sau brad uscat pentru a proteja plantele perene și trandafirii de gerul iernii.";
this.tips["12-11"] = "Verifică și repară gardurile și suporturile pentru plante cățărătoare înainte de ninsori abundente.";
this.tips["12-12"] = "Taie și adună crengi de ilex, brad sau vâsc pentru decorațiuni festive naturale și pentru a aerisi tufele.";
this.tips["12-13"] = "Ridică și împarte tufele de rubarbă, replantând secțiunile sănătoase în sol bogat în compost.";
this.tips["12-14"] = "Pune paie sau brad la baza rădăcinilor de pătrunjel și țelină rămase în grădină pentru a le proteja de îngheț.";
this.tips["12-15"] = "Verifică și curăță sistemele de drenaj la ghivecele cu plante perene. Înlocuiește stratul superior de substrat cu compost proaspăt.";
this.tips["12-16"] = "Prunează vița de vie: taie ramurile laterale la 1-2 ochi de la tulpina principală pentru a stimula producția de struguri anul viitor.";
this.tips["12-17"] = "Planifică rotația culturilor pentru anul viitor. Notează ce a mers bine și ce nu în grădina acestui an.";
this.tips["12-18"] = "Verifică plantele de interior pentru dăunători (păianjeni roșii, afide). Șterge frunzele cu o cârpă umedă.";
this.tips["12-19"] = "Folosește zilele mai calde pentru a aerisi sera sau spațiile protejate, prevenind astfel apariția mucegaiului.";
this.tips["12-20"] = "Pregătește compostul pentru iarnă: întoarce grămada și acoperă cu folie sau paie pentru a păstra căldura.";
this.tips["12-21"] = "Sărbătorește solstițiul de iarnă: aprinde o lumânare în grădină și bucură-te de liniștea naturii în repaus.";
this.tips["12-22"] = "Verifică depozitele de semințe. Uscă-le bine și păstrează-le la răcoare, în pungi de hârtie.";
this.tips["12-23"] = "Adună și depozitează frunzele uscate pentru mulci sau compost la primăvară.";
this.tips["12-24"] = "Pregătește decorațiuni naturale pentru Crăciun: coronițe din crengi de brad, conuri și scorțișoară.";
this.tips["12-25"] = "Bucură-te de sărbători! Admiră grădina și planifică noi proiecte pentru anul viitor.";
this.tips["12-26"] = "Dacă vremea permite, sapă ușor solul în jurul pomilor pentru a aerisi rădăcinile.";
this.tips["12-27"] = "Verifică și curăță adăposturile pentru unelte și materiale de grădinărit.";
this.tips["12-28"] = "Întoarce compostul pentru a accelera descompunerea și a obține un îngrășământ bogat la primăvară.";
this.tips["12-29"] = "Planifică achiziția de semințe și materiale pentru sezonul următor. Consultă cataloagele și fă o listă de dorințe.";
this.tips["12-30"] = "Verifică plantele de interior și ajustează udarea în funcție de temperatură și umiditate.";
this.tips["12-31"] = "Încheie anul cu recunoștință pentru roadele grădinii. Scrie-ți obiectivele pentru grădinăritul de anul viitor și bucură-te de familie!";  }
  getTodaysTip() {
    const today = new Date().toISOString().slice(5,10);
    return this.tips[today] || "Îngrijește grădina cu dragoste și răbdare!";
  }
}

const tracker = new UsageTracker();
const tips = new DailyTipProvider();

const menuBtn      = document.getElementById('menuBtn');
const menuDropdown = document.getElementById('menuDropdown');
const chatWindow   = document.getElementById('chatWindow');
const messageInput = document.getElementById('messageInput');
const sendBtn      = document.getElementById('sendBtn');
const attachBtn    = document.getElementById('attachBtn');
const micBtn       = document.getElementById('micBtn');
const fileInput    = document.querySelector('input[type="file"]'); // reuse existing

menuBtn.addEventListener('click', () => {
  menuDropdown.classList.toggle('hidden');
});
sendBtn.addEventListener('click', send);
attachBtn.addEventListener('click', () => fileInput.click());
micBtn.addEventListener('click', startSpeechRecognition);

// — Settings modal —
settingsBtn.addEventListener('click', () => {
  settingsModal.classList.remove('hidden');
});
settingsCloseBtn.addEventListener('click', () => {
  settingsModal.classList.add('hidden');
});
fontSizeRange.addEventListener('input', e => {
  document.documentElement.style.setProperty('--chat-font-scale', e.target.value);
  localStorage.setItem('fontScale', e.target.value);
});

// — Help modal —
helpBtn.addEventListener('click', () => helpModal.classList.remove('hidden'));
helpCloseBtn.addEventListener('click', () => helpModal.classList.add('hidden'));

// — Social links —
socialBtn.addEventListener('click', () => {
  window.open('https://facebook.com/yourpage', '_blank');
});

// — Premium flow —
premiumBtn.addEventListener('click', () => {
  alert('Pagina Premium urmează să fie implementată.');
  // TODO: redirect to /premium or show purchase modal
});

// — Invite friends (same as existing shareReferral) —
inviteBtnMenu.addEventListener('click', shareReferral);

// — Privacy policy —
privacyBtn.addEventListener('click', () => {
  window.open('/politica-confidentialitate', '_blank');
});

const savedScale = parseFloat(localStorage.getItem('fontScale') || '1');
document.documentElement.style.setProperty('--chat-font-scale', savedScale);
fontSizeRange.value = savedScale;

const tipDiv = document.getElementById('tip');
tipDiv.textContent = tips.getTodaysTip();
const welcomeDiv = document.getElementById('welcome');
const editNameBtn = document.getElementById('edit-name');

function getUserName(){
  return localStorage.getItem('user_name') || '';
}

function setUserName(name){
  localStorage.setItem('user_name', name);
}

function showWelcome(first){
  const name = getUserName();
  if(name){
    const last = name.trim().split(/\s+/).pop();
    if(first){
      welcomeDiv.textContent = `Bun venit, ${last}!`;
    } else {
      welcomeDiv.textContent = `Bine ai revenit, ${last}!`;
    }
    editNameBtn.classList.remove('hidden');
  } else {
    welcomeDiv.textContent = '';
    editNameBtn.classList.add('hidden');
  }
}
showWelcome();
const imageInput = document.getElementById('image');
// sendBtn already defined above
const shareBtn = document.getElementById('share-ref');
const myRefDiv = document.getElementById('my-ref');
const darkToggle = document.getElementById('dark-mode-toggle');
const ttsToggle = document.getElementById('toggle-tts');
const installBtn = document.getElementById('install');
const feedbackBtn = document.getElementById('feedback-btn');
const feedbackModal = document.getElementById('feedback-modal');
const feedbackText = document.getElementById('feedback-text');
const sendFeedbackBtn = document.getElementById('send-feedback');
const usageDiv = document.getElementById('counter');
const offlineDiv = document.getElementById('offline');
const trophyList = document.getElementById('trophy-list');
const trophiesDiv = document.getElementById('trophies');
if(usageDiv) usageDiv.textContent = tracker.status();

function displayReferral(){
  if(!myRefDiv) return;
  const link = getReferralLink();
  myRefDiv.textContent = `Referralul tău: ${link}`;
  myRefDiv.addEventListener('click', ()=>{
    navigator.clipboard.writeText(link);
    alert('Link copiat!');
  });
}

function updateOnlineStatus(){
  if(offlineDiv)
    offlineDiv.classList.toggle('hidden', navigator.onLine);
}
window.addEventListener('online', updateOnlineStatus);
window.addEventListener('offline', updateOnlineStatus);
updateOnlineStatus();

if(installBtn){
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    installBtn.style.display = 'inline-block';
  });

  installBtn.addEventListener('click', () => {
    if (deferredPrompt) {
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then(() => {
        deferredPrompt = null;
      });
    }
  });
}

window.addEventListener('offline', () => {
  alert('⚠️ Ești offline. Aplicația va funcționa doar cu fișierele cache.');
});

if(darkToggle){
  darkToggle.checked = localStorage.getItem('dark') === 'true';
  if (darkToggle.checked) {
    document.body.classList.add('dark');
  }
  darkToggle.addEventListener('change', () => {
    const enabled = darkToggle.checked;
    document.body.classList.toggle('dark', enabled);
    localStorage.setItem('dark', enabled);
  });
}
if(ttsToggle){
  ttsToggle.checked = localStorage.getItem('tts') !== 'false';
  ttsToggle.addEventListener('change', () => {
    localStorage.setItem('tts', ttsToggle.checked);
  });
}
logUsage('open');

function speak(text) {
  const tts = document.getElementById('toggle-tts');
  if (tts && !tts.checked) return;
  if (!tts) return;
  const utter = new SpeechSynthesisUtterance(text);
  utter.lang = 'ro-RO';
  utter.rate = 0.9;
  speechSynthesis.speak(utter);
}

function showTyping() {
  const typing = document.createElement('div');
  typing.id = 'typing';
  typing.textContent = 'GPT scrie...';
  typing.className = 'message bot';␊
  chatWindow.appendChild(typing);
}

function hideTyping() {
  const el = document.getElementById('typing');
  if (el) el.remove();
}

function startSpeechRecognition() {
  if (!window.webkitSpeechRecognition) return alert('Browserul nu suportă speech-to-text');
  const sr = new webkitSpeechRecognition();
  sr.lang = 'ro-RO';
  sr.onresult = e => {
    messageInput.value = e.results[0][0].transcript;
  };
  sr.start();
}

function addMessage(text, user) {
  const div = document.createElement('div');
  div.className = 'message ' + (user ? 'user' : 'bot');
  div.textContent = text;
  chatWindow.appendChild(div);
  chatWindow.scrollTop = chatWindow.scrollHeight;
}

async function send() {
   const text = messageInput.value.trim();
  const file = imageInput.files[0];
  if(!text && !file) return;
  if(text && !tracker.canMakeTextAPICall()) {
    alert('Limita de întrebări atinsă.');
    return;
  }
  if(file && !tracker.canMakeImageAPICall()) {
    alert('Limita de imagini atinsă.');
    return;
  }
  addMessage(text || 'Imagine trimisă', true)
  showTyping();
  let base64 = null;
  if(file){
    const reader = new FileReader();
    base64 = await new Promise(res=>{
      reader.onload=()=>res(reader.result.split(',')[1]);
      reader.readAsDataURL(file);
    });
  }
  lastQuestion = text;
  lastImage = base64;
  const body = {};
  if(text) body.message = text;
  if(base64) body.image = base64;
  body.device_hash = getDeviceHash();
  body.app_version = APP_VERSION;
  const name = getUserName();
  if(name) body.user_name = name;
  const ref = getReferrerCode();
  if(ref && !hasUsedReferrer()){
    body.ref_code = ref;
  }
  try {
    const resp = await fetch('https://gabeetzu-project.onrender.com/process-image.php', {
      method:'POST',
      headers:{
        'Content-Type':'application/json'
      },
      body: JSON.stringify(body)
    });
     const data = await resp.json();
    hideTyping();
    if(data.success){
      const text = data.response?.text ?? data.response?.message ?? 'Eroare răspuns';
      addMessage(text, false);
      if(text){
        tracker.recordTextAPICall();
        incStat('q');
      }
      if(base64){
        tracker.recordImageAPICall();
        incStat('img');
      }
      usageDiv.textContent = tracker.status();
      if(data.reward === 'referral_success' && !hasUsedReferrer()){
        tracker.FREE_TEXT_LIMIT += 3;
        markReferrerUsed();
        alert('Cod de recomandare folosit! Ai primit 3 întrebări bonus.');
        usageDiv.textContent = tracker.status();
      }
      Trophies.checkAll();
      Trophies.render();
      fetch(BASE_URL + 'log-usage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          device_hash: getDeviceHash(),
          message: text || '[image]',
          image: !!base64,
          platform: 'PWA',
          app_version: APP_VERSION,
          ref_code: localStorage.getItem('referrer_code') || null,
          timestamp: new Date().toISOString()
        })
      });
      logUsage('send');
    } else {
      addMessage('⚠️ ' + (data.error || 'Eroare.'), false);
      logUsage('send_fail');
      hideTyping();
    }
  } catch(e){
    hideTyping();
    addMessage('⚠️ ' + e.message, false);
    console.error('Fetch error:', e);
    alert('Eroare la trimitere: ' + e.message);
    logUsage('send_fail');
  }
}

function shareReferral(){
  const link = getReferralLink();
  if(navigator.share){
    navigator.share({title:'GospodApp', text:'Îți recomand GospodApp', url:link});
  } else {
    navigator.clipboard.writeText(link);
    alert('Link copiat: ' + link);
  }
  localStorage.setItem('referred_friend', 'true');
  Trophies.checkAll();
  Trophies.render();
}

function sendFeedback(){
  const correction = feedbackText.value.trim();
  if(!correction){
    alert('Completează corectarea');
    return;
  }
  const body = {
    original: lastQuestion,
    correction,
    image: lastImage || 'none',
    device_hash: getDeviceHash(),
    timestamp: new Date().toISOString()
  };
  const name = getUserName();
  if(name) body.user_name = name;
  fetch('https://gabeetzu-project.onrender.com/submit_feedback.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(body)
  }).then(r=>r.json()).then(d=>{
    if(d.success){
      alert('Mulțumim pentru corectare!');
      feedbackText.value='';
      feedbackModal.classList.add('hidden');
      logUsage('feedback');
    } else {
      alert('Eroare: ' + (d.error||''));
    }
  }).catch(e => {
    console.error('Feedback error:', e);
    alert(e.message);
  });
}
shareBtn.addEventListener('click', shareReferral);
feedbackBtn.addEventListener('click', () => {
  feedbackModal.classList.remove('hidden');
});
sendFeedbackBtn.addEventListener('click', sendFeedback);

// GDPR modal
const modal = document.getElementById('gdpr-modal');
if(localStorage.getItem('consent') !== 'true') {
  modal.style.display='flex';
}
document.getElementById('gdpr-accept').addEventListener('click', ()=>{
  localStorage.setItem('consent','true');
  modal.style.display='none';
});

// Profile setup
const profileModal = document.getElementById('profile-modal');
const nameInput = document.getElementById('name-input');
const saveNameBtn = document.getElementById('save-name');
if(!getUserName()) {
  profileModal.style.display = 'flex';
}
saveNameBtn.addEventListener('click', () => {
  const name = nameInput.value.trim();
  if(name.length > 1){
    setUserName(name);
    profileModal.style.display = 'none';
    showWelcome(true);
  }
});
editNameBtn.addEventListener('click', () => {
  localStorage.removeItem('user_name');
  location.reload();
});

Trophies.checkAll();
Trophies.render();
displayReferral();
