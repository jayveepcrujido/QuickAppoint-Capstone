<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>ID Verification - LGU QuickAppoint</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>
    body {
      background: linear-gradient(135deg, #ffffffff);
      min-height: 100vh;
      padding: 20px 0;
    }
    .main-container {
      max-width: 900px;
      margin: 0 auto;
    }
    .card {
      border-radius: 15px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.1);
      margin-bottom: 20px;
    }
    .video-container {
      position: relative;
      background: #000;
      border-radius: 10px;
      overflow: hidden;
      margin: 20px 0;
      min-height: 400px;
    }
    #video, #videoSelfie {
      width: 100%;
      height: auto;
      display: block;
    }
    #idCanvas, #selfieCanvas {
      display: none;
    }
    
    /* ID Frame Overlay */
    .id-frame-overlay {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 85%;
      height: 60%;
      border: 3px solid #fff;
      border-radius: 15px;
      pointer-events: none;
      transition: border-color 0.3s;
    }
    .id-frame-overlay.detecting {
      border-color: #ffc107;
      animation: pulse 1s infinite;
    }
    .id-frame-overlay.detected {
      border-color: #28a745;
      box-shadow: 0 0 20px rgba(40, 167, 69, 0.6);
    }
    
    /* Face Circle Overlay */
    .face-circle-overlay {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 300px;
      height: 400px;
      border: 3px solid #fff;
      border-radius: 50%;
      pointer-events: none;
      transition: border-color 0.3s;
    }
    .face-circle-overlay.detecting {
      border-color: #ffc107;
      animation: pulse 1s infinite;
    }
    .face-circle-overlay.face-detected {
      border-color: #28a745;
      box-shadow: 0 0 20px rgba(40, 167, 69, 0.6);
    }
    
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }
    
    .status-indicator {
      position: absolute;
      top: 20px;
      left: 50%;
      transform: translateX(-50%);
      padding: 10px 20px;
      border-radius: 25px;
      font-weight: bold;
      z-index: 10;
      background: rgba(0, 0, 0, 0.7);
      color: white;
      min-width: 250px;
      text-align: center;
    }
    .status-scanning { background: rgba(255, 193, 7, 0.9); color: #000; }
    .status-detected { background: rgba(40, 167, 69, 0.9); color: #fff; }
    .status-processing { background: rgba(23, 162, 184, 0.9); color: #fff; }
    
    .countdown {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      font-size: 120px;
      font-weight: bold;
      color: #28a745;
      text-shadow: 0 0 20px rgba(40, 167, 69, 0.8);
      z-index: 100;
      animation: countdownPulse 1s ease-in-out;
    }
    
    @keyframes countdownPulse {
      0% { transform: translate(-50%, -50%) scale(0.5); opacity: 0; }
      50% { transform: translate(-50%, -50%) scale(1.2); opacity: 1; }
      100% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
    }
    
    .preview-image {
      max-width: 100%;
      border-radius: 10px;
      margin-top: 10px;
      border: 3px solid #28a745;
    }
    .progress-steps {
      display: flex;
      justify-content: space-between;
      margin-bottom: 30px;
    }
    .step {
      flex: 1;
      text-align: center;
      padding: 10px;
      position: relative;
    }
    .step::after {
      content: '';
      position: absolute;
      top: 20px;
      left: 50%;
      width: 100%;
      height: 2px;
      background: #ddd;
      z-index: -1;
    }
    .step:last-child::after {
      display: none;
    }
    .step-circle {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #ddd;
      color: #666;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      margin-bottom: 5px;
    }
    .step.active .step-circle {
      background: #667eea;
      color: #fff;
    }
    .step.completed .step-circle {
      background: #28a745;
      color: #fff;
    }
    .hidden {
      display: none;
    }
    .instruction-box {
      background: rgba(255, 193, 7, 0.1);
      border-left: 4px solid #ffc107;
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 5px;
    }
    .blink-indicator {
      position: absolute;
      top: 80px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(0, 0, 0, 0.8);
      color: white;
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 14px;
      z-index: 10;
    }
    .spinner-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.7);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 200;
    }
  </style>
</head>
<body>
  <div class="main-container">
    <div class="card">
      <div class="card-body">
        <h2 class="text-center mb-4">ID Verification Process</h2>
        
        <!-- Progress Steps -->
        <div class="progress-steps">
          <div class="step active" id="step1">
            <div class="step-circle">1</div>
            <div>Select ID</div>
          </div>
          <div class="step" id="step2">
            <div class="step-circle">2</div>
            <div>Scan ID</div>
          </div>
          <div class="step" id="step3">
            <div class="step-circle">3</div>
            <div>Take Selfie</div>
          </div>
          <div class="step" id="step4">
            <div class="step-circle">4</div>
            <div>Verify Match</div>
          </div>
        </div>

        <!-- Step 1: ID Type Selection -->
        <div id="idTypeSection">
          <h4>Step 1: Select Your Valid ID Type</h4>
          <div class="form-group">
            <select id="idType" class="form-control form-control-lg">
              <option value="">-- Choose ID Type --</option>
              <option value="drivers_license">Driver's License</option>
              <option value="national_id">National ID (PhilSys)</option>
              <option value="passport">Philippine Passport</option>
              <option value="umid">UMID / SSS</option>
              <option value="postal_id">Postal ID</option>
              <option value="tin_id">TIN ID</option>
            </select>
          </div>
          <div class="alert alert-info">
            <strong><i class="fas fa-lightbulb"></i> Smart Capture Tips:</strong>
            <ul class="mb-0">
              <li>Your ID will be <strong>automatically captured</strong> when properly positioned</li>
              <li>Your selfie will be <strong>automatically captured</strong> when your face is detected</li>
              <li>You'll need to blink during selfie capture to verify it's you</li>
              <li>Ensure good lighting for best results</li>
            </ul>
          </div>
          <button id="startCameraBtn" class="btn btn-primary btn-lg btn-block" disabled>
            <i class="fas fa-camera"></i> Start Smart Verification
          </button>
          <button id="manualUploadBtn" class="btn btn-secondary btn-lg btn-block mt-2">
            <i class="fas fa-upload"></i> Manual Upload Instead
          </button>
        </div>

        <!-- Step 2: ID Capture -->
        <div id="idCaptureSection" class="hidden">
          <h4>Step 2: Position Your ID in the Frame</h4>
          <div class="instruction-box">
            <strong><i class="fas fa-info-circle"></i> Instructions:</strong>
            <ul class="mb-0">
              <li>Place your ID flat within the rectangular frame</li>
              <li>Ensure all corners are visible and text is clear</li>
              <li>The system will <strong>automatically capture</strong> when properly positioned</li>
              <li>Hold steady for best results</li>
            </ul>
          </div>
          <div class="video-container">
            <div class="status-indicator status-scanning" id="idStatus">
              Position ID within frame...
            </div>
            <video id="video" autoplay playsinline></video>
            <div class="id-frame-overlay" id="idFrameOverlay"></div>
            <canvas id="idCanvas"></canvas>
          </div>
          <div id="idPreview" class="hidden">
            <h5><i class="fas fa-check-circle text-success"></i> ID Captured Successfully!</h5>
            <img id="idPreviewImg" class="preview-image" alt="ID Preview">
            <div class="mt-3">
              <button id="retakeIdBtn" class="btn btn-warning btn-lg">
                <i class="fas fa-redo"></i> Retake
              </button>
              <button id="confirmIdBtn" class="btn btn-success btn-lg">
                <i class="fas fa-arrow-right"></i> Continue to Selfie
              </button>
            </div>
          </div>
        </div>

        <!-- Step 3: Selfie Capture -->
        <div id="selfieCaptureSection" class="hidden">
          <h4>Step 3: Take Your Live Selfie</h4>
          <div class="instruction-box">
            <strong><i class="fas fa-info-circle"></i> Instructions:</strong>
            <ul class="mb-0">
              <li>Position your face within the circle</li>
              <li><strong>Blink naturally</strong> when prompted to prove you're live</li>
              <li>The system will automatically capture when ready</li>
              <li>Look directly at the camera and ensure good lighting</li>
            </ul>
          </div>
          <div class="video-container">
            <div class="status-indicator status-scanning" id="selfieStatus">
              Detecting face...
            </div>
            <div class="blink-indicator hidden" id="blinkIndicator">
              👁️ Please blink naturally
            </div>
            <video id="videoSelfie" autoplay playsinline></video>
            <div class="face-circle-overlay" id="faceCircleOverlay"></div>
            <canvas id="selfieCanvas"></canvas>
          </div>
          <div id="selfiePreview" class="hidden">
            <h5><i class="fas fa-check-circle text-success"></i> Selfie Captured Successfully!</h5>
            <img id="selfiePreviewImg" class="preview-image" alt="Selfie Preview">
            <div class="mt-3">
              <button id="retakeSelfieBtn" class="btn btn-warning btn-lg">
                <i class="fas fa-redo"></i> Retake
              </button>
              <button id="confirmSelfieBtn" class="btn btn-success btn-lg">
                <i class="fas fa-arrow-right"></i> Verify & Continue
              </button>
            </div>
          </div>
        </div>

        <!-- Step 4: Verification -->
        <div id="verificationSection" class="hidden">
          <h4>Step 4: Verifying Your Identity</h4>
          <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status" style="width: 4rem; height: 4rem;">
              <span class="sr-only">Processing...</span>
            </div>
            <h5 class="mt-4">Please wait...</h5>
            <p class="text-muted" id="verificationMessage">Analyzing your ID and selfie</p>
            <div class="progress mt-3" style="height: 25px;">
              <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" 
                   style="width: 0%" id="progressBar"></div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs"></script>
  <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/blazeface"></script>
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/opencv.js@1.2.1"></script>
  
  <script>
    let videoStream = null;
    let idImageData = null;
    let selfieImageData = null;
    let selectedIdType = '';
    let blazefaceModel = null;
    let isCapturing = false;
    let idDetectionInterval = null;
    let faceDetectionInterval = null;
    let blinkDetectionActive = false;
    let previousEyeState = null;
    let blinkCount = 0;
    let faceDetectedFrames = 0;
    const REQUIRED_BLINKS = 1;
    const FACE_STABLE_FRAMES = 10;

    // Enable start button when ID type is selected
    document.getElementById('idType').addEventListener('change', function() {
      selectedIdType = this.value;
      document.getElementById('startCameraBtn').disabled = !selectedIdType;
    });

    // Load face detection model
    async function loadModels() {
      try {
        blazefaceModel = await blazeface.load();
        console.log('Face detection model loaded');
      } catch (error) {
        console.error('Error loading models:', error);
      }
    }

    // Start camera verification
    document.getElementById('startCameraBtn').addEventListener('click', async function() {
      this.disabled = true;
      this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting camera...';
      
      await loadModels();
      
      try {
        videoStream = await navigator.mediaDevices.getUserMedia({ 
          video: { 
            facingMode: 'environment',
            width: { ideal: 1280 },
            height: { ideal: 720 }
          } 
        });
        document.getElementById('video').srcObject = videoStream;
        
        // Wait for video to be ready
        document.getElementById('video').onloadedmetadata = () => {
          updateStep(2);
          showSection('idCaptureSection');
          startIDDetection();
        };
        
      } catch (err) {
        alert('Camera access denied. Please allow camera access or use manual upload.');
        console.error(err);
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-camera"></i> Start Smart Verification';
      }
    });

    // ID Detection using edge detection and contour analysis
    function startIDDetection() {
      const video = document.getElementById('video');
      const canvas = document.getElementById('idCanvas');
      const ctx = canvas.getContext('2d');
      const overlay = document.getElementById('idFrameOverlay');
      const status = document.getElementById('idStatus');
      
      let stableFrames = 0;
      const REQUIRED_STABLE_FRAMES = 15;
      
      idDetectionInterval = setInterval(() => {
        if (isCapturing) return;
        
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0);
        
        // Get the region inside the frame overlay
        const frameWidth = video.videoWidth * 0.85;
        const frameHeight = video.videoHeight * 0.6;
        const frameX = (video.videoWidth - frameWidth) / 2;
        const frameY = (video.videoHeight - frameHeight) / 2;
        
        const imageData = ctx.getImageData(frameX, frameY, frameWidth, frameHeight);
        
        // Simple edge detection: check if there's enough contrast (indicates ID card)
        const hasID = detectIDInFrame(imageData);
        
        if (hasID) {
          stableFrames++;
          overlay.classList.add('detecting');
          status.textContent = `ID detected! Hold steady... (${stableFrames}/${REQUIRED_STABLE_FRAMES})`;
          status.className = 'status-indicator status-detected';
          
          if (stableFrames >= REQUIRED_STABLE_FRAMES) {
            overlay.classList.remove('detecting');
            overlay.classList.add('detected');
            status.textContent = 'Perfect! Capturing...';
            captureIDAutomatically();
          }
        } else {
          stableFrames = Math.max(0, stableFrames - 2);
          overlay.classList.remove('detected');
          if (stableFrames > 0) {
            overlay.classList.add('detecting');
          } else {
            overlay.classList.remove('detecting');
          }
          status.textContent = 'Position ID within frame...';
          status.className = 'status-indicator status-scanning';
        }
      }, 100);
    }

    // Detect if ID card is in frame
    function detectIDInFrame(imageData) {
      const data = imageData.data;
      let edges = 0;
      let total = 0;
      
      // Sample pixels and check for edges (high contrast changes)
      for (let i = 0; i < data.length; i += 40) {
        if (i + 4 < data.length) {
          const r1 = data[i];
          const g1 = data[i + 1];
          const b1 = data[i + 2];
          const r2 = data[i + 4];
          const g2 = data[i + 5];
          const b2 = data[i + 6];
          
          const diff = Math.abs(r1 - r2) + Math.abs(g1 - g2) + Math.abs(b1 - b2);
          
          if (diff > 80) edges++;
          total++;
        }
      }
      
      const edgeRatio = edges / total;
      
      // Check if there's moderate edge density (typical of ID cards)
      // Not too few (empty frame) and not too many (cluttered background)
      return edgeRatio > 0.15 && edgeRatio < 0.45;
    }

    // Automatically capture ID
    function captureIDAutomatically() {
      if (isCapturing) return;
      isCapturing = true;
      
      clearInterval(idDetectionInterval);
      
      showCountdown(3, () => {
        const video = document.getElementById('video');
        const canvas = document.getElementById('idCanvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0);
        
        idImageData = canvas.toDataURL('image/jpeg', 0.95);
        document.getElementById('idPreviewImg').src = idImageData;
        document.getElementById('video').style.display = 'none';
        document.getElementById('idFrameOverlay').style.display = 'none';
        document.getElementById('idStatus').style.display = 'none';
        document.getElementById('idPreview').classList.remove('hidden');
        
        isCapturing = false;
      });
    }

    // Show countdown
    function showCountdown(seconds, callback) {
      const container = document.querySelector('#idCaptureSection .video-container') || 
                        document.querySelector('#selfieCaptureSection .video-container');
      
      let count = seconds;
      const countdownEl = document.createElement('div');
      countdownEl.className = 'countdown';
      countdownEl.textContent = count;
      container.appendChild(countdownEl);
      
      const interval = setInterval(() => {
        count--;
        if (count > 0) {
          countdownEl.textContent = count;
          // Re-trigger animation
          countdownEl.style.animation = 'none';
          setTimeout(() => {
            countdownEl.style.animation = 'countdownPulse 1s ease-in-out';
          }, 10);
        } else {
          clearInterval(interval);
          countdownEl.remove();
          callback();
        }
      }, 1000);
    }

    // Retake ID
    document.getElementById('retakeIdBtn').addEventListener('click', function() {
      document.getElementById('idPreview').classList.add('hidden');
      document.getElementById('video').style.display = 'block';
      document.getElementById('idFrameOverlay').style.display = 'block';
      document.getElementById('idStatus').style.display = 'block';
      idImageData = null;
      startIDDetection();
    });

    // Confirm ID and move to selfie
    document.getElementById('confirmIdBtn').addEventListener('click', async function() {
      // Stop ID camera
      if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
      }
      
      updateStep(3);
      showSection('selfieCaptureSection');
      
      // Start selfie camera (front-facing)
      try {
        videoStream = await navigator.mediaDevices.getUserMedia({ 
          video: { 
            facingMode: 'user',
            width: { ideal: 1280 },
            height: { ideal: 720 }
          } 
        });
        document.getElementById('videoSelfie').srcObject = videoStream;
        
        document.getElementById('videoSelfie').onloadedmetadata = () => {
          startFaceDetection();
        };
      } catch (err) {
        alert('Camera access denied for selfie.');
        console.error(err);
      }
    });

    // Face and blink detection
    async function startFaceDetection() {
      const video = document.getElementById('videoSelfie');
      const overlay = document.getElementById('faceCircleOverlay');
      const status = document.getElementById('selfieStatus');
      const blinkIndicator = document.getElementById('blinkIndicator');
      
      faceDetectionInterval = setInterval(async () => {
        if (isCapturing || !blazefaceModel) return;
        
        try {
          const predictions = await blazefaceModel.estimateFaces(video, false);
          
          if (predictions.length > 0) {
            faceDetectedFrames++;
            overlay.classList.add('face-detected');
            
            if (faceDetectedFrames >= FACE_STABLE_FRAMES && !blinkDetectionActive) {
              blinkDetectionActive = true;
              status.textContent = 'Face detected! Please blink naturally...';
              blinkIndicator.classList.remove('hidden');
              startBlinkDetection(predictions[0]);
            }
          } else {
            faceDetectedFrames = 0;
            blinkDetectionActive = false;
            blinkCount = 0;
            overlay.classList.remove('face-detected');
            status.textContent = 'Detecting face...';
            status.className = 'status-indicator status-scanning';
            blinkIndicator.classList.add('hidden');
          }
        } catch (error) {
          console.error('Face detection error:', error);
        }
      }, 200);
    }

    // Blink detection
    function startBlinkDetection(face) {
      const status = document.getElementById('selfieStatus');
      const blinkIndicator = document.getElementById('blinkIndicator');
      
      // Calculate eye aspect ratio (simple approximation)
      const leftEye = face.landmarks[0];
      const rightEye = face.landmarks[1];
      
      // Simple blink detection: check vertical eye distance
      const eyeHeight = Math.abs(leftEye[1] - rightEye[1]);
      const isEyeClosed = eyeHeight < 3; // Threshold for closed eyes
      
      if (previousEyeState === false && isEyeClosed) {
        // Eye closed (potential blink)
      } else if (previousEyeState === true && !isEyeClosed) {
        // Eye opened (blink completed)
        blinkCount++;
        blinkIndicator.textContent = `👁️ Blink detected! (${blinkCount}/${REQUIRED_BLINKS})`;
        
        if (blinkCount >= REQUIRED_BLINKS) {
          status.textContent = 'Verified! Capturing...';
          status.className = 'status-indicator status-detected';
          blinkIndicator.classList.add('hidden');
          captureSelfieAutomatically();
        }
      }
      
      previousEyeState = !isEyeClosed;
    }

    // Automatically capture selfie
    function captureSelfieAutomatically() {
      if (isCapturing) return;
      isCapturing = true;
      
      clearInterval(faceDetectionInterval);
      
      showCountdown(3, () => {
        const video = document.getElementById('videoSelfie');
        const canvas = document.getElementById('selfieCanvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0);
        
        selfieImageData = canvas.toDataURL('image/jpeg', 0.95);
        document.getElementById('selfiePreviewImg').src = selfieImageData;
        document.getElementById('videoSelfie').style.display = 'none';
        document.getElementById('faceCircleOverlay').style.display = 'none';
        document.getElementById('selfieStatus').style.display = 'none';
        document.getElementById('blinkIndicator').style.display = 'none';
        document.getElementById('selfiePreview').classList.remove('hidden');
        
        isCapturing = false;
      });
    }

    // Retake Selfie
    document.getElementById('retakeSelfieBtn').addEventListener('click', function() {
      document.getElementById('selfiePreview').classList.add('hidden');
      document.getElementById('videoSelfie').style.display = 'block';
      document.getElementById('faceCircleOverlay').style.display = 'block';
      document.getElementById('selfieStatus').style.display = 'block';
      selfieImageData = null;
      blinkCount = 0;
      faceDetectedFrames = 0;
      blinkDetectionActive = false;
      previousEyeState = null;
      startFaceDetection();
    });

    // Confirm Selfie and verify
    document.getElementById('confirmSelfieBtn').addEventListener('click', function() {
      // Stop camera
      if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
      }
      
      updateStep(4);
      showSection('verificationSection');
      processVerification();
    });

    // Process verification
    function processVerification() {
      const progressBar = document.getElementById('progressBar');
      const message = document.getElementById('verificationMessage');
      
      let progress = 0;
      const steps = [
        { progress: 20, text: 'Extracting text from ID...' },
        { progress: 40, text: 'Analyzing ID authenticity...' },
        { progress: 60, text: 'Comparing faces...' },
        { progress: 80, text: 'Verifying match...' },
        { progress: 100, text: 'Finalizing...' }
      ];
      
      let currentStep = 0;
      const interval = setInterval(() => {
        if (currentStep < steps.length) {
          progressBar.style.width = steps[currentStep].progress + '%';
          message.textContent = steps[currentStep].text;
          currentStep++;
        } else {
          clearInterval(interval);
          submitToServer();
        }
      }, 1000);
    }

    // Submit to server
    function submitToServer() {
      const formData = new FormData();
      formData.append('id_type', selectedIdType);
      formData.append('id_image', dataURLtoBlob(idImageData), 'id.jpg');
      formData.append('selfie_image', dataURLtoBlob(selfieImageData), 'selfie.jpg');
      formData.append('camera_capture', 'true');

      fetch('process_camera_verification.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success || data.face_match_passed) {
          // Redirect to review page
          window.location.href = 'register_review.php';
        } else {
          alert('Verification failed: ' + data.message + '\n\nPlease try again or use manual upload.');
          location.reload();
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Verification failed. Please try again or use manual upload.');
        location.reload();
      });
    }

    // Manual upload redirect
    document.getElementById('manualUploadBtn').addEventListener('click', function() {
      window.location.href = 'register_upload.php';
    });

    // Helper functions
    function updateStep(stepNumber) {
      for (let i = 1; i <= 4; i++) {
        const step = document.getElementById('step' + i);
        if (i < stepNumber) {
          step.classList.add('completed');
          step.classList.remove('active');
        } else if (i === stepNumber) {
          step.classList.add('active');
          step.classList.remove('completed');
        } else {
          step.classList.remove('active', 'completed');
        }
      }
    }

    function showSection(sectionId) {
      const sections = ['idTypeSection', 'idCaptureSection', 'selfieCaptureSection', 'verificationSection'];
      sections.forEach(id => {
        document.getElementById(id).classList.add('hidden');
      });
      document.getElementById(sectionId).classList.remove('hidden');
    }

    function dataURLtoBlob(dataURL) {
      const arr = dataURL.split(',');
      const mime = arr[0].match(/:(.*?);/)[1];
      const bstr = atob(arr[1]);
      let n = bstr.length;
      const u8arr = new Uint8Array(n);
      while (n--) {
        u8arr[n] = bstr.charCodeAt(n);
      }
      return new Blob([u8arr], { type: mime });
    }

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
      if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
      }
      if (idDetectionInterval) clearInterval(idDetectionInterval);
      if (faceDetectionInterval) clearInterval(faceDetectionInterval);
    });
  </script>
</body>
</html>