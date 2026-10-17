# AI Billing demo-video recorder

Playwright records the application workflow, Edge TTS creates an Indian-English narration, and FFmpeg produces the final MP4.

## GST pilot

Start the local preview in one terminal:

```powershell
npm.cmd run e2e:server
```

Record in another terminal:

```powershell
node scripts/demo-videos/record-gst-basics.mjs
```

Tamil narration and captions:

```powershell
npm.cmd run demo:gst:tamil
```

Output:

`marketing/demo-videos/ai-billing-gst-basics-english.mp4`

`marketing/demo-videos/ai-billing-gst-basics-tamil.mp4`

Install the narration helper once with `py -m pip install edge-tts`. The generated voice is suitable for review and early customer demos. An approved human English, Tamil, or Hindi voice can replace the narration without re-recording the screen.

