<?php
// Load environment variables using PHP dotenv
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Serve the appId for frontend
if (isset($_GET['config'])) {
    header('Content-Type: application/json');
    echo json_encode(['appId' => getenv('APP_ID')]);
    exit;
}

// Proxy API calls securely
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['proxy'])) {
    $appSecret = getenv('APP_SECRET');
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['url']) || !isset($input['body'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }

    $ch = curl_init($input['url']);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($input['body']));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $appSecret,
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    http_response_code($httpCode);
    echo $result;
    exit;
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Secure Cloudflare Calls Demo</title>
    <script
      src="https://cdnjs.cloudflare.com/ajax/libs/webrtc-adapter/8.1.2/adapter.min.js"
      integrity="sha512-l40eBFtXx+ve5RryIELC3y6/OM6Nu89mLGQd7fg1C93tN6XrkC3supb+/YiD/Y+B8P37kdJjtG1MT1kOO2VzxA=="
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
    ></script>
  </head>
  <body>
    <div class="grid">
      <h1>Secure Calls Echo Demo</h1>
      <div>
        <h2>Local Stream</h2>
        <video id="local-video" autoplay muted></video>
      </div>
      <div>
        <h2>Remote Echo Stream</h2>
        <video id="remote-video" autoplay></video>
      </div>
    </div>
    <script type="module">
      async function initializeApp() {
        // Fetch the appId securely from the server
        const response = await fetch('?config');
        const { appId } = await response.json();

        // Securely proxy API requests through the backend
        async function sendRequest(url, body) {
          const res = await fetch('?proxy', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url, body }),
          });
          return res.json();
        }

        class CallsApp {
          constructor(appId, basePath = 'https://rtc.live.cloudflare.com/v1') {
            this.prefixPath = `${basePath}/apps/${appId}`;
          }

          async newSession(offerSDP) {
            const url = `${this.prefixPath}/sessions/new`;
            const body = {
              sessionDescription: {
                type: 'offer',
                sdp: offerSDP,
              },
            };
            const result = await sendRequest(url, body);
            return result;
          }
        }

        const app = new CallsApp(appId);
        const localStream = await navigator.mediaDevices.getUserMedia({
          video: true,
          audio: true,
        });
        const localVideoElement = document.getElementById('local-video');
        localVideoElement.srcObject = localStream;

        const pc = new RTCPeerConnection({
          iceServers: [{ urls: 'stun:stun.cloudflare.com:3478' }],
        });
        localStream.getTracks().forEach(track => pc.addTrack(track, localStream));

        await pc.setLocalDescription(await pc.createOffer());
        const newSessionResult = await app.newSession(pc.localDescription.sdp);
        await pc.setRemoteDescription(
          new RTCSessionDescription(newSessionResult.sessionDescription)
        );

        // Example code continues...
      }

      // Start the app
      initializeApp();
    </script>
    <style>
      html {
        font-family: sans-serif;
        background: white;
        color: black;
      }
      body, h1, h2 { margin: 0; }
      h1 { font-size: 1.5rem; grid-column: 1 / -1; }
      h2 { font-size: 1rem; }
      video { width: 100%; }
      .grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1fr 1fr;
      }
      @media (max-width: 500px) {
        .grid { grid-template-columns: 1fr; }
      }
    </style>
  </body>
</html>
