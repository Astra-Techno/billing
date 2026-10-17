import { chromium } from '@playwright/test'
import { spawnSync } from 'node:child_process'
import { mkdirSync, existsSync, rmSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const outputDir = path.join(root, 'marketing', 'demo-videos')
const workDir = path.join(outputDir, '.work-gst-basics')
const baseURL = process.env.BASE_URL || 'http://127.0.0.1:5173'
const language = process.argv[2]?.toLowerCase() === 'tamil' ? 'tamil' : 'english'
mkdirSync(workDir, { recursive: true })
mkdirSync(outputDir, { recursive: true })

const englishSegments = [
  {
    caption: 'GST billing becomes simple when the shop and customer states are correct.',
    narration: 'Welcome to AI Billing. Let us understand how GST works while creating a bill. The application needs your shop state and the customer state to select the correct tax automatically.',
    action: async page => page.goto(`${baseURL}/help`, { waitUntil: 'networkidle' }),
  },
  {
    caption: 'Open Set up GST from the visual help guides.',
    narration: 'Open the visual help guides and choose Set up GST. This lesson shows the four settings you should check before making your first tax invoice.',
    action: async page => page.getByRole('button', { name: /Set up GST/i }).click(),
  },
  {
    caption: 'Add GSTIN → Set shop state → Set customer state → Select Tax Invoice',
    narration: 'First enter the fifteen character GSTIN. Next choose the state where your shop is registered. Save the customer state in the customer record, and select Tax Invoice whenever GST must be charged.',
    action: async page => page.locator('#visual-guide-detail').scrollIntoViewIfNeeded(),
  },
  {
    caption: 'Same state: 18% GST becomes 9% CGST + 9% SGST.',
    narration: 'When your shop and the place of supply are in the same state, eighteen percent GST is divided equally. On one thousand rupees, the bill shows ninety rupees CGST and ninety rupees SGST.',
    action: async page => page.getByText('Which tax will be used?').scrollIntoViewIfNeeded(),
  },
  {
    caption: 'Different state: the full 18% is shown as IGST.',
    narration: 'When the customer place of supply is in another state, the complete tax is shown as IGST. On one thousand rupees at eighteen percent, the IGST amount is one hundred and eighty rupees.',
    action: async page => page.getByText('OTHER STATE').click(),
  },
  {
    caption: 'Bill of Supply does not charge GST. Verify special cases with your accountant.',
    narration: 'For exempt supplies or eligible non tax billing, choose Bill of Supply and GST will not be charged. Check unusual transactions with your accountant before finalising the invoice.',
    action: async page => page.getByText(/Bill of Supply:/).scrollIntoViewIfNeeded(),
  },
]

const tamilSegments = [
  {
    caption: 'கடை மற்றும் வாடிக்கையாளர் மாநிலம் சரியாக இருந்தால் GST கணக்கீடு மிகவும் எளிது.',
    narration: 'AI Billing மென்பொருளுக்கு உங்களை வரவேற்கிறோம். பில் உருவாக்கும்போது ஜி எஸ் டி எவ்வாறு செயல்படுகிறது என்பதை இப்போது பார்ப்போம். சரியான வரியைத் தானாகத் தேர்வு செய்ய, உங்கள் கடையின் மாநிலமும் வாடிக்கையாளரின் மாநிலமும் மென்பொருளில் சரியாக இருக்க வேண்டும்.',
    action: async page => page.goto(`${baseURL}/help`, { waitUntil: 'networkidle' }),
  },
  {
    caption: 'Visual Help Guides பகுதியில் Set up GST என்பதைத் தேர்வு செய்யுங்கள்.',
    narration: 'விஷுவல் ஹெல்ப் கைட்ஸ் பகுதியில், செட் அப் ஜி எஸ் டி என்பதைத் தேர்வு செய்யுங்கள். முதல் வரி பில்லை உருவாக்கும் முன்பு சரிபார்க்க வேண்டிய நான்கு அமைப்புகள் இங்கே காட்டப்படுகின்றன.',
    action: async page => page.getByRole('button', { name: /Set up GST/i }).click(),
  },
  {
    caption: 'GSTIN சேர்க்கவும் → கடை மாநிலம் → வாடிக்கையாளர் மாநிலம் → Tax Invoice',
    narration: 'முதலில் பதினைந்து எழுத்துகள் கொண்ட ஜி எஸ் டி ஐ என் எண்ணை உள்ளிடுங்கள். அடுத்து உங்கள் கடை பதிவு செய்யப்பட்ட மாநிலத்தைத் தேர்வு செய்யுங்கள். வாடிக்கையாளரின் மாநிலத்தை அவருடைய விவரங்களில் சேமித்து, ஜி எஸ் டி வசூலிக்க வேண்டிய பில்லுக்கு டாக்ஸ் இன்வாய்ஸ் என்பதைத் தேர்வு செய்யுங்கள்.',
    action: async page => page.locator('#visual-guide-detail').scrollIntoViewIfNeeded(),
  },
  {
    caption: 'ஒரே மாநிலம்: 18% GST = 9% CGST + 9% SGST',
    narration: 'உங்கள் கடையும் விற்பனை நடைபெறும் இடமும் ஒரே மாநிலத்தில் இருந்தால், பதினெட்டு சதவீத ஜி எஸ் டி இரண்டு சம பாகங்களாகப் பிரியும். ஆயிரம் ரூபாய் விற்பனைக்கு தொண்ணூறு ரூபாய் சி ஜி எஸ் டி மற்றும் தொண்ணூறு ரூபாய் எஸ் ஜி எஸ் டி காட்டப்படும்.',
    action: async page => page.getByText('Which tax will be used?').scrollIntoViewIfNeeded(),
  },
  {
    caption: 'வேறு மாநிலம்: முழு 18% வரியும் IGST ஆகக் காட்டப்படும்.',
    narration: 'வாடிக்கையாளரின் விற்பனை மாநிலம் வேறாக இருந்தால், முழு வரியும் ஐ ஜி எஸ் டி ஆகக் காட்டப்படும். ஆயிரம் ரூபாய்க்கு பதினெட்டு சதவீதம் என்றால், நூற்று எண்பது ரூபாய் ஐ ஜி எஸ் டி கணக்கிடப்படும்.',
    action: async page => page.getByText('OTHER STATE').click(),
  },
  {
    caption: 'Bill of Supply-ல் GST வசூலிக்கப்படாது. சந்தேகம் இருந்தால் கணக்காளரிடம் சரிபார்க்கவும்.',
    narration: 'வரி விலக்கு பெற்ற பொருட்கள் அல்லது தகுதியான வரியில்லா பில்லுக்கு, பில் ஆஃப் சப்ளை என்பதைத் தேர்வு செய்யுங்கள். இதில் ஜி எஸ் டி வசூலிக்கப்படாது. வழக்கத்திற்கு மாறான பரிவர்த்தனைகளை இறுதி செய்வதற்கு முன்பு உங்கள் கணக்காளரிடம் சரிபார்க்கவும்.',
    action: async page => page.getByText(/Bill of Supply:/).scrollIntoViewIfNeeded(),
  },
]

const segments = language === 'tamil' ? tamilSegments : englishSegments

const narration = segments.map(item => item.narration).join(' ')
const audioPath = path.join(workDir, 'narration.mp3')
const voiceName = language === 'tamil' ? 'ta-IN-PallaviNeural' : 'en-IN-NeerjaNeural'
const voice = spawnSync('py', ['-m', 'edge_tts', '--voice', voiceName, '--rate=-6%', '--text', narration, '--write-media', audioPath], { stdio: 'inherit' })
if (voice.status !== 0 || !existsSync(audioPath)) throw new Error('Narration generation failed. Confirm internet access and the edge-tts package.')

const probe = spawnSync('ffprobe', ['-v', 'error', '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', audioPath], { encoding: 'utf8' })
const audioDurationMs = Math.round(Number.parseFloat(probe.stdout.trim()) * 1_000)
if (!Number.isFinite(audioDurationMs)) throw new Error('Could not measure narration duration.')
const totalCharacters = segments.reduce((sum, item) => sum + item.narration.length, 0)
const segmentDurations = segments.map(item => Math.max(5_000, Math.round(audioDurationMs * item.narration.length / totalCharacters + 650)))
const browser = await chromium.launch({ headless: true, ...(process.platform === 'win32' ? { channel: 'msedge' } : {}) })
const context = await browser.newContext({
  viewport: { width: 1280, height: 720 },
  deviceScaleFactor: 1,
  recordVideo: { dir: workDir, size: { width: 1280, height: 720 } },
})
const page = await context.newPage()

async function showCaption(text) {
  await page.evaluate(caption => {
    let box = document.getElementById('demo-video-caption')
    if (!box) {
      box = document.createElement('div')
      box.id = 'demo-video-caption'
      Object.assign(box.style, {
        position: 'fixed', zIndex: '2147483647', left: '50%', bottom: '24px', transform: 'translateX(-50%)',
        width: 'min(940px, calc(100% - 48px))', padding: '15px 22px', borderRadius: '16px',
        background: 'rgba(8,15,32,.90)', color: 'white', border: '1px solid rgba(255,255,255,.24)',
        boxShadow: '0 18px 50px rgba(0,0,0,.28)', backdropFilter: 'blur(16px)',
        font: '700 22px/1.35 Inter, Segoe UI, sans-serif', textAlign: 'center', transition: 'opacity .25s ease',
      })
      document.body.appendChild(box)
    }
    box.textContent = caption
  }, text)
}

for (const [index, segment] of segments.entries()) {
  await segment.action(page)
  await showCaption(segment.caption)
  await page.waitForTimeout(segmentDurations[index])
}
await page.waitForTimeout(800)

const video = page.video()
await context.close()
await browser.close()
const rawVideo = await video.path()
const finalPath = path.join(outputDir, `ai-billing-gst-basics-${language}.mp4`)
const encode = spawnSync('ffmpeg', ['-y', '-i', rawVideo, '-i', audioPath, '-c:v', 'libx264', '-preset', 'medium', '-crf', '20', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '160k', '-shortest', '-movflags', '+faststart', finalPath], { stdio: 'inherit' })
if (encode.status !== 0 || !existsSync(finalPath)) throw new Error('MP4 creation failed.')

rmSync(workDir, { recursive: true, force: true })
console.log(`Created ${finalPath}`)

