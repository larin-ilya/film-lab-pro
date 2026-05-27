<?php
// Конфигурация
define('PHOTOS_DIR', __DIR__ . '/photos');
define('INDEX_FILE', __DIR__ . '/index.json');

// Создаём папку для фото
if (!file_exists(PHOTOS_DIR)) {
    mkdir(PHOTOS_DIR, 0755, true);
}

// Получаем путь запроса
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// 📸 Отдача фото по прямым ссылкам
if (preg_match('#^/i/(.+)$#', $path, $matches)) {
    $key = $matches[1];
    $photo_path = PHOTOS_DIR . '/' . $key;
    
    if (file_exists($photo_path)) {
        $mime = mime_content_type($photo_path);
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=31536000');
        readfile($photo_path);
        exit;
    } else {
        http_response_code(404);
        echo "Photo not found";
        exit;
    }
}


// 🖼️ API галереи
if ($path === '/gallery') {
    header('Content-Type: application/json');
    
    $index = [];
    if (file_exists(INDEX_FILE)) {
        $content = file_get_contents(INDEX_FILE);
        if ($content) {
            $index = json_decode($content, true) ?: [];
        }
    }
    
    // Берём последние 50
    $gallery = array_slice($index, 0, 50);
    echo json_encode($gallery);
    exit;
}





// 📤 Upload endpoint
if ($path === '/upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No file uploaded');
        }
        
        $file = $_FILES['file'];
        
        // Генерируем уникальное имя
        $key = time() . '_' . uniqid() . '.jpg';
        $photo_path = PHOTOS_DIR . '/' . $key;
        
        // Сохраняем файл
        if (!move_uploaded_file($file['tmp_name'], $photo_path)) {
            throw new Exception('Failed to save file');
        }
        
        // Обновляем индекс
        $index = [];
        if (file_exists(INDEX_FILE)) {
            $content = file_get_contents(INDEX_FILE);
            if ($content) {
                $index = json_decode($content, true) ?: [];
            }
        }
        
        // Добавляем новое фото в начало
        array_unshift($index, [
            'id' => str_replace('.jpg', '', $key),
            'url' => "/i/{$key}",
            'time' => time() * 1000
        ]);
        
        // Храним только последние 200
        if (count($index) > 200) {
            $index = array_slice($index, 0, 200);
        }
        
        // Сохраняем индекс
        file_put_contents(INDEX_FILE, json_encode($index, JSON_PRETTY_PRINT));
        
        $file_url = "https://" . $_SERVER['HTTP_HOST'] . "/i/{$key}";
        
        echo json_encode([
            'url' => $file_url,
            'key' => $key,
            'direct' => $file_url
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
// 🏠 Главная страница - отдаём HTML
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🎞️ Film Lab Pro</title>
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  background: #0a0a0a;
  color: #f0e6d3;
  font-family: 'Courier New', monospace;
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  padding: 20px;
  background-image: 
    radial-gradient(ellipse at 20% 50%, rgba(212,163,115,0.03) 0%, transparent 50%),
    radial-gradient(ellipse at 80% 20%, rgba(139,90,43,0.05) 0%, transparent 50%);
}

.container {
  display: flex;
  gap: 20px;
  max-width: 1200px;
  width: 100%;
  flex-wrap: wrap;
  justify-content: center;
}

.card {
  width: 460px;
  background: linear-gradient(180deg, #1a1a1a 0%, #141414 100%);
  padding: 24px;
  border-radius: 16px;
  border: 1px solid #2a2a2a;
  box-shadow: 0 30px 80px rgba(0,0,0,0.8), inset 0 1px 0 rgba(255,255,255,0.05);
}

.gallery-panel {
  width: 460px;
  background: linear-gradient(180deg, #1a1a1a 0%, #141414 100%);
  padding: 24px;
  border-radius: 16px;
  border: 1px solid #2a2a2a;
  box-shadow: 0 30px 80px rgba(0,0,0,0.8), inset 0 1px 0 rgba(255,255,255,0.05);
  max-height: 90vh;
  overflow-y: auto;
}

.header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid #2a2a2a;
}

.logo {
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, #d4a373, #b88352);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}

.title {
  font-size: 18px;
  font-weight: bold;
  color: #d4a373;
  letter-spacing: 1px;
}

.subtitle {
  font-size: 11px;
  color: #666;
  margin-top: 2px;
}

canvas {
  width: 100%;
  border-radius: 8px;
  margin-top: 16px;
  border: 1px solid #222;
  display: block;
}

.row {
  display: flex;
  gap: 8px;
  margin-top: 12px;
}

select, button {
  font-family: 'Courier New', monospace;
  padding: 10px 14px;
  border-radius: 8px;
  border: 1px solid #333;
  background: #1a1a1a;
  color: #d4a373;
  cursor: pointer;
  font-size: 13px;
  transition: all 0.2s;
}

select {
  width: 100%;
  margin-top: 12px;
}

select:hover {
  border-color: #d4a373;
  background: #222;
}

button {
  flex: 1;
  text-transform: uppercase;
  letter-spacing: 1px;
  font-weight: bold;
}

button:hover {
  background: #2a2a2a;
  border-color: #d4a373;
  transform: translateY(-1px);
}

button:active {
  transform: translateY(0);
}

.btn-primary {
  background: linear-gradient(135deg, #d4a373, #b88352);
  color: #0a0a0a;
  border: none;
}

.btn-primary:hover {
  background: linear-gradient(135deg, #e0b483, #c49362);
}

input[type="file"] {
  width: 100%;
  padding: 10px;
  background: #1a1a1a;
  border: 1px dashed #333;
  border-radius: 8px;
  color: #888;
  cursor: pointer;
  margin-top: 8px;
}

input[type="file"]::file-selector-button {
  background: #2a2a2a;
  border: 1px solid #444;
  padding: 6px 12px;
  border-radius: 6px;
  color: #d4a373;
  cursor: pointer;
  margin-right: 10px;
}

input[type="range"] {
  width: 100%;
  margin-top: 12px;
  -webkit-appearance: none;
  background: #2a2a2a;
  height: 4px;
  border-radius: 2px;
  outline: none;
}

input[type="range"]::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 16px;
  height: 16px;
  background: #d4a373;
  border-radius: 50%;
  cursor: pointer;
  border: 2px solid #0a0a0a;
}

.label {
  font-size: 11px;
  color: #888;
  margin-top: 16px;
  display: block;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.value {
  float: right;
  color: #d4a373;
}

.status {
  margin-top: 12px;
  padding: 10px;
  border-radius: 8px;
  font-size: 12px;
  display: none;
  animation: fadeIn 0.3s;
}

.status.show {
  display: block;
}

.status.success {
  background: rgba(212,163,115,0.1);
  border: 1px solid rgba(212,163,115,0.3);
  color: #d4a373;
}

.status.error {
  background: rgba(255,80,80,0.1);
  border: 1px solid rgba(255,80,80,0.3);
  color: #ff5050;
}

.url-box {
  margin-top: 8px;
  display: none;
}

.url-box.show {
  display: block;
}

.url-input {
  width: 100%;
  padding: 10px;
  background: #0a0a0a;
  border: 1px solid #333;
  border-radius: 8px;
  color: #d4a373;
  font-size: 11px;
  cursor: pointer;
  font-family: 'Courier New', monospace;
}

.gallery-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.gallery-title {
  font-size: 16px;
  font-weight: bold;
  color: #d4a373;
  letter-spacing: 1px;
}

.gallery-count {
  font-size: 11px;
  color: #666;
}

#gallery {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 15px;
}

.gallery-item {
  position: relative;
  width: calc(33.333% - 6px);
  aspect-ratio: 1;
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.2s;
  border: 1px solid #2a2a2a;
}

.gallery-item:hover {
  transform: scale(1.05);
  border-color: #d4a373;
  box-shadow: 0 8px 25px rgba(212,163,115,0.2);
  z-index: 10;
}

.gallery-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.gallery-item .photo-time {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  background: linear-gradient(transparent, rgba(0,0,0,0.8));
  padding: 4px 6px;
  font-size: 9px;
  color: #d4a373;
  opacity: 0;
  transition: opacity 0.2s;
}

.gallery-item:hover .photo-time {
  opacity: 1;
}

.empty-gallery {
  width: 100%;
  text-align: center;
  padding: 40px 20px;
  color: #444;
  font-style: italic;
}

.empty-gallery .icon {
  font-size: 48px;
  margin-bottom: 10px;
  opacity: 0.3;
}

.empty-gallery p {
  font-size: 13px;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-5px); }
  to { opacity: 1; transform: translateY(0); }
}

.film-strip {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: repeating-linear-gradient(90deg, #0a0a0a 0px, #0a0a0a 20px, #1a1a1a 20px, #1a1a1a 22px);
  z-index: 100;
}

.gallery-panel::-webkit-scrollbar {
  width: 6px;
}

.gallery-panel::-webkit-scrollbar-track {
  background: #1a1a1a;
  border-radius: 3px;
}

.gallery-panel::-webkit-scrollbar-thumb {
  background: #2a2a2a;
  border-radius: 3px;
}

.gallery-panel::-webkit-scrollbar-thumb:hover {
  background: #d4a373;
}

@media (max-width: 960px) {
  .container {
    flex-direction: column;
    align-items: center;
  }
  
  .card, .gallery-panel {
    width: 100%;
    max-width: 460px;
  }
}
</style>
</head>
<body>

<div class="film-strip"></div>

<div class="container">
  <div class="card">
    <div class="header">
      <div class="logo">📷</div>
      <div>
        <div class="title">FILM LAB PRO</div>
        <div class="subtitle">2003年 · アナログ写真</div>
      </div>
    </div>

    <input type="file" id="fileInput" accept="image/*">

    <select id="presetSelect">
      <option value="kodak">🎞️ Kodak Gold 200</option>
      <option value="fuji">🗻 Fuji Superia 400</option>
      <option value="cinestill">🌙 Cinestill 800T</option>
      <option value="bw">🖤 Ilford HP5 Plus</option>
    </select>

    <label class="label">Temperature <span class="value" id="warmthVal">+15%</span></label>
    <input type="range" id="warmthSlider" min="0.5" max="1.5" step="0.01" value="1.15">

    <label class="label">Contrast <span class="value" id="contrastVal">+10%</span></label>
    <input type="range" id="contrastSlider" min="0.5" max="1.5" step="0.01" value="1.1">

    <label class="label">Film Grain <span class="value" id="grainVal">35%</span></label>
    <input type="range" id="grainSlider" min="0" max="1" step="0.01" value="0.35">

    <label class="label">Exposure <span class="value" id="exposureVal">0 EV</span></label>
    <input type="range" id="exposureSlider" min="0.5" max="1.5" step="0.01" value="1">

    <div class="row">
      <button id="developBtn" class="btn-primary">🔄 Develop</button>
      <button id="downloadBtn">💾 Save</button>
      <button id="uploadBtn">☁️ Share</button>
    </div>

    <div id="statusMsg" class="status"></div>
    
    <div id="urlBox" class="url-box">
      <input class="url-input" id="urlInput" readonly onclick="this.select()" placeholder="Your photo URL will appear here...">
    </div>

    <canvas id="photoCanvas"></canvas>
  </div>

  <div class="gallery-panel">
    <div class="gallery-header">
      <div class="gallery-title">🖼️ Community Gallery</div>
      <div class="gallery-count" id="galleryCount">0 photos</div>
    </div>
    <button onclick="loadGallery()" style="width: 100%; margin-bottom: 10px;">📸 Refresh Gallery</button>
    <div id="gallery">
      <div class="empty-gallery">
        <div class="icon">📭</div>
        <p>No photos yet.<br>Be the first to share!</p>
      </div>
    </div>
  </div>
</div>

<script>
const photoCanvas = document.getElementById("photoCanvas");
const ctx = photoCanvas.getContext("2d");
let img = new Image();
let originalFile = null;
let processedBlob = null;

document.getElementById("fileInput").onchange = function(e) {
  originalFile = e.target.files[0];
  if (!originalFile) return;
  
  const reader = new FileReader();
  reader.onload = function(ev) {
    img.src = ev.target.result;
    resetUI();
  };
  reader.readAsDataURL(originalFile);
};

img.onload = function() {
  photoCanvas.width = img.width;
  photoCanvas.height = img.height;
  ctx.drawImage(img, 0, 0);
  autoRender();
};

document.getElementById("warmthSlider").oninput = function() {
  updateLabels();
  if (img.src) autoRender();
};

document.getElementById("contrastSlider").oninput = function() {
  updateLabels();
  if (img.src) autoRender();
};

document.getElementById("grainSlider").oninput = function() {
  updateLabels();
  if (img.src) autoRender();
};

document.getElementById("exposureSlider").oninput = function() {
  updateLabels();
  if (img.src) autoRender();
};

document.getElementById("presetSelect").onchange = function() {
  if (img.src) {
    applyPresetValues();
    autoRender();
  }
};

document.getElementById("developBtn").onclick = function() {
  renderImage();
};

document.getElementById("downloadBtn").onclick = function() {
  downloadImage();
};

document.getElementById("uploadBtn").onclick = function() {
  uploadToHosting();
};

function applyPresetValues() {
  var presets = {
    kodak: { warmth: 1.15, contrast: 1.1, grain: 0.35, exposure: 1.05 },
    fuji: { warmth: 1.05, contrast: 1.05, grain: 0.25, exposure: 1 },
    cinestill: { warmth: 1.25, contrast: 1.2, grain: 0.45, exposure: 1.1 },
    bw: { warmth: 1, contrast: 1.3, grain: 0.4, exposure: 1 }
  };
  
  var selectedPreset = document.getElementById("presetSelect").value;
  var p = presets[selectedPreset];
  
  document.getElementById("warmthSlider").value = p.warmth;
  document.getElementById("contrastSlider").value = p.contrast;
  document.getElementById("grainSlider").value = p.grain;
  document.getElementById("exposureSlider").value = p.exposure;
  
  updateLabels();
}

function updateLabels() {
  var warmthVal = parseFloat(document.getElementById("warmthSlider").value);
  var contrastVal = parseFloat(document.getElementById("contrastSlider").value);
  var grainVal = parseFloat(document.getElementById("grainSlider").value);
  var exposureVal = parseFloat(document.getElementById("exposureSlider").value);
  
  document.getElementById("warmthVal").textContent = ((warmthVal - 1) * 100).toFixed(0) + "%";
  document.getElementById("contrastVal").textContent = ((contrastVal - 1) * 100).toFixed(0) + "%";
  document.getElementById("grainVal").textContent = (grainVal * 100).toFixed(0) + "%";
  document.getElementById("exposureVal").textContent = ((exposureVal - 1) * 100).toFixed(1) + " EV";
}

function autoRender() {
  requestAnimationFrame(renderImage);
}

function renderImage() {
  if (!img.complete) return;

  ctx.filter = "none";
  ctx.drawImage(img, 0, 0);

  var imageData = ctx.getImageData(0, 0, photoCanvas.width, photoCanvas.height);
  var pixelData = imageData.data;

  var currentPreset = document.getElementById("presetSelect").value;
  var warmthFactor = parseFloat(document.getElementById("warmthSlider").value);
  var contrastFactor = parseFloat(document.getElementById("contrastSlider").value);
  var exposureFactor = parseFloat(document.getElementById("exposureSlider").value);
  var grainAmount = parseFloat(document.getElementById("grainSlider").value);

  for (var i = 0; i < pixelData.length; i += 4) {
    var red = pixelData[i];
    var green = pixelData[i + 1];
    var blue = pixelData[i + 2];

    red *= exposureFactor;
    green *= exposureFactor;
    blue *= exposureFactor;

    red *= warmthFactor;
    blue /= warmthFactor;

    red = (red - 128) * contrastFactor + 128;
    green = (green - 128) * contrastFactor + 128;
    blue = (blue - 128) * contrastFactor + 128;

    if (currentPreset === "bw") {
      var gray = (red + green + blue) / 3;
      red = gray;
      green = gray;
      blue = gray;
    }

    pixelData[i] = Math.max(0, Math.min(255, red));
    pixelData[i + 1] = Math.max(0, Math.min(255, green));
    pixelData[i + 2] = Math.max(0, Math.min(255, blue));
  }

  ctx.putImageData(imageData, 0, 0);
  addLightLeakEffect();
  if (grainAmount > 0) addGrainEffect(grainAmount);
  if (currentPreset === "cinestill") addHalationEffect();
  addFilmBorderEffect();
  addTimestampEffect();
  
  ctx.filter = "contrast(1.05) saturate(1.15)";
  ctx.drawImage(photoCanvas, 0, 0);
  ctx.filter = "none";
}

function addLightLeakEffect() {
  var gradient = ctx.createLinearGradient(0, 0, photoCanvas.width * 0.3, photoCanvas.height * 0.3);
  gradient.addColorStop(0, "rgba(255, 140, 60, 0.12)");
  gradient.addColorStop(0.5, "rgba(255, 80, 20, 0.05)");
  gradient.addColorStop(1, "rgba(0, 0, 0, 0)");
  
  ctx.fillStyle = gradient;
  ctx.fillRect(0, 0, photoCanvas.width, photoCanvas.height);
}

function addGrainEffect(intensity) {
  var imageData = ctx.getImageData(0, 0, photoCanvas.width, photoCanvas.height);
  var pixelData = imageData.data;
  
  for (var i = 0; i < pixelData.length; i += 4) {
    var noise = (Math.random() - 0.5) * intensity * 50;
    pixelData[i] = Math.max(0, Math.min(255, pixelData[i] + noise));
    pixelData[i + 1] = Math.max(0, Math.min(255, pixelData[i + 1] + noise));
    pixelData[i + 2] = Math.max(0, Math.min(255, pixelData[i + 2] + noise));
  }
  
  ctx.putImageData(imageData, 0, 0);
}

function addHalationEffect() {
  var imageData = ctx.getImageData(0, 0, photoCanvas.width, photoCanvas.height);
  var pixelData = imageData.data;
  
  for (var i = 0; i < pixelData.length; i += 4) {
    var brightness = (pixelData[i] + pixelData[i + 1] + pixelData[i + 2]) / 3;
    if (brightness > 200) {
      pixelData[i] = Math.min(255, pixelData[i] + 20);
      pixelData[i + 1] = Math.max(0, pixelData[i + 1] - 5);
      pixelData[i + 2] = Math.max(0, pixelData[i + 2] - 10);
    }
  }
  
  ctx.putImageData(imageData, 0, 0);
}

function addFilmBorderEffect() {
  ctx.strokeStyle = "rgba(0,0,0,0.3)";
  ctx.lineWidth = photoCanvas.width * 0.02;
  ctx.strokeRect(0, 0, photoCanvas.width, photoCanvas.height);
  
  var gradient = ctx.createRadialGradient(
    photoCanvas.width / 2, photoCanvas.height / 2, photoCanvas.width * 0.4,
    photoCanvas.width / 2, photoCanvas.height / 2, photoCanvas.width * 0.7
  );
  gradient.addColorStop(0, "rgba(0,0,0,0)");
  gradient.addColorStop(1, "rgba(0,0,0,0.3)");
  ctx.fillStyle = gradient;
  ctx.fillRect(0, 0, photoCanvas.width, photoCanvas.height);
}

function addTimestampEffect() {
  var now = new Date();
  var timestamp = now.getFullYear() + "." + 
    String(now.getMonth() + 1).padStart(2, "0") + "." + 
    String(now.getDate()).padStart(2, "0") + " " +
    String(now.getHours()).padStart(2, "0") + ":" + 
    String(now.getMinutes()).padStart(2, "0");
  
  ctx.font = (photoCanvas.width * 0.025) + 'px "Courier New", monospace';
  ctx.fillStyle = "rgba(255, 200, 100, 0.8)";
  ctx.fillText(timestamp, photoCanvas.width * 0.03, photoCanvas.height - photoCanvas.height * 0.03);
}

function downloadImage() {
  renderImage();
  
  photoCanvas.toBlob(function(blob) {
    var url = URL.createObjectURL(blob);
    var link = document.createElement("a");
    link.href = url;
    link.download = "film_" + Date.now() + ".jpg";
    link.click();
    URL.revokeObjectURL(url);
  }, "image/jpeg", 0.92);
}

async function uploadToHosting() {
  var statusElement = document.getElementById("statusMsg");
  var urlBoxElement = document.getElementById("urlBox");
  var urlInputElement = document.getElementById("urlInput");
  
  if (!img.complete || !img.src) {
    showStatusMessage("❌ Please select a photo first", "error");
    return;
  }
  
  showStatusMessage("📤 Developing & uploading...", "success");
  
  try {
    renderImage();
    
    var blob = await new Promise(function(resolve) {
      photoCanvas.toBlob(resolve, "image/jpeg", 0.9);
    });
    
    var formData = new FormData();
    formData.append("file", blob, "film_" + Date.now() + ".jpg");
    
    var response = await fetch("/upload", {
      method: "POST",
      body: formData
    });
    
    if (!response.ok) throw new Error("Upload failed");
    
    var result = await response.json();
    
    urlBoxElement.classList.add("show");
    urlInputElement.value = result.url;
    
    await navigator.clipboard.writeText(result.url);
    showStatusMessage("✅ Uploaded! URL copied to clipboard", "success");
    
    processedBlob = blob;
    
    setTimeout(function() {
      loadGallery();
    }, 500);
    
  } catch (error) {
    showStatusMessage("❌ Upload failed: " + error.message, "error");
  }
}

async function loadGallery() {
  try {
    var response = await fetch("/gallery");
    var photos = await response.json();
    
    var galleryContainer = document.getElementById("gallery");
    var galleryCount = document.getElementById("galleryCount");
    
    if (photos.length === 0) {
      galleryContainer.innerHTML = '<div class="empty-gallery"><div class="icon">📭</div><p>No photos yet.<br>Be the first to share!</p></div>';
      galleryCount.textContent = "0 photos";
      return;
    }
    
    galleryCount.textContent = photos.length + " photo" + (photos.length !== 1 ? "s" : "");
    galleryContainer.innerHTML = "";
    
    photos.forEach(function(photo) {
      var item = document.createElement("div");
      item.className = "gallery-item";
      
      var img = document.createElement("img");
      img.src = photo.url;
      img.alt = "Film photo";
      img.loading = "lazy";
      
      var timeLabel = document.createElement("div");
      timeLabel.className = "photo-time";
      
      var date = new Date(photo.time);
      timeLabel.textContent = date.toLocaleDateString("en-US", { 
        month: "short", 
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit"
      });
      
      item.appendChild(img);
      item.appendChild(timeLabel);
      
      item.onclick = function() {
        window.open(photo.url, "_blank");
      };
      
      galleryContainer.appendChild(item);
    });
    
  } catch (error) {
    console.error("Failed to load gallery:", error);
  }
}

function showStatusMessage(message, type) {
  var statusElement = document.getElementById("statusMsg");
  statusElement.textContent = message;
  statusElement.className = "status show " + type;
  
  if (type === "success") {
    setTimeout(function() {
      statusElement.className = "status";
    }, 5000);
  }
}

function resetUI() {
  document.getElementById("urlBox").classList.remove("show");
  document.getElementById("statusMsg").className = "status";
  processedBlob = null;
}

applyPresetValues();
updateLabels();
loadGallery();

setInterval(function() {
  loadGallery();
}, 30000);
</script>

</body>
</html>
