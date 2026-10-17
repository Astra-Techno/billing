import { chromium } from '@playwright/test'
import { spawnSync } from 'node:child_process'
import { mkdirSync, existsSync, rmSync, writeFileSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const outputDir = path.join(root, 'marketing', 'demo-videos')
const workDir = path.join(outputDir, '.work-store-setup')
const authPath = path.join(outputDir, 'demo-auth.json')
const storePath = path.join(outputDir, 'demo-store.json')
const baseURL = process.env.BASE_URL || 'http://127.0.0.1:5173'
rmSync(workDir, { recursive: true, force: true })
mkdirSync(workDir, { recursive: true })
mkdirSync(outputDir, { recursive: true })

const stamp = Date.now()
const demo = {
  owner: 'Murugan Kumar', business: 'Sri Murugan Stores',
  email: `tamil.demo.${stamp}@example.com`, mobile: `9${String(stamp).slice(-9)}`,
  password: 'Demo@12345', state: 'Tamil Nadu', gstin: '33ABCDE1234F1Z5',
}

const segments = [
  { caption: 'பகுதி 1: புதிய கடையை உருவாக்குவது எப்படி?', narration: 'AI Billing பயிற்சியின் முதல் பகுதிக்கு உங்களை வரவேற்கிறோம். இப்போது உண்மையான செயலியில், ஸ்ரீ முருகன் ஸ்டோர்ஸ் என்ற மாதிரி கடையை புதிதாக உருவாக்கப் போகிறோம்.' },
  { caption: 'உரிமையாளர் பெயர், மின்னஞ்சல் மற்றும் மொபைல் எண்ணை உள்ளிடுங்கள்.', narration: 'முதலில் கடை உரிமையாளரின் முழுப் பெயர், பயன்படுத்தக்கூடிய மின்னஞ்சல் முகவரி மற்றும் பத்து இலக்க மொபைல் எண்ணை உள்ளிடுங்கள். இந்த தகவல்கள் உங்கள் கணக்கை அடையாளம் காணப் பயன்படும்.' },
  { caption: 'குறைந்தது 8 எழுத்துகள் கொண்ட பாதுகாப்பான கடவுச்சொல்லை அமைக்கவும்.', narration: 'அடுத்து குறைந்தது எட்டு எழுத்துகள் கொண்ட பாதுகாப்பான கடவுச்சொல்லை இரண்டு இடங்களிலும் ஒரே மாதிரியாக உள்ளிடுங்கள். பின்னர் கண்டினியூ பொத்தானை அழுத்துங்கள்.' },
  { caption: 'Business / Shop Name பகுதியில் உங்கள் கடையின் பெயரை எழுதுங்கள்.', narration: 'இப்போது பிசினஸ் அல்லது ஷாப் நேம் பகுதியில், பில்லில் காட்ட வேண்டிய கடையின் பெயரை உள்ளிடுங்கள். நமது மாதிரி கடையின் பெயர் ஸ்ரீ முருகன் ஸ்டோர்ஸ்.' },
  { caption: 'Business Type மற்றும் State ஆகியவற்றை சரியாகத் தேர்வு செய்யுங்கள்.', narration: 'உங்கள் நிறுவன அமைப்புக்கு ஏற்ற பிசினஸ் டைப்பை தேர்வு செய்யுங்கள். இந்த கடை தனிநபர் உரிமம் என்பதால் புரொப்ரைட்டர்ஷிப் தேர்வு செய்யப்பட்டுள்ளது. அடுத்து மாநிலமாக தமிழ்நாட்டை தேர்வு செய்கிறோம்.' },
  { caption: 'GST பதிவு இருந்தால் GSTIN எண்ணை இப்போதே சேர்க்கலாம்.', narration: 'உங்கள் கடைக்கு ஜி எஸ் டி பதிவு இருந்தால், பதினைந்து எழுத்துகள் கொண்ட ஜி எஸ் டி ஐ என் எண்ணை உள்ளிடலாம். பதிவு இல்லையெனில் இந்த இடத்தை காலியாக விட்டுவிட்டு பின்னர் அமைப்புகளில் சேர்க்கலாம்.' },
  { caption: 'Create Free Account அழுத்தியதும் கடை கணக்கு உருவாகும்.', narration: 'அனைத்து தகவல்களையும் சரிபார்த்து கிரியேட் ஃப்ரீ அக்கவுண்ட் பொத்தானை அழுத்துங்கள். சில விநாடிகளில் கடை கணக்கு உருவாக்கப்பட்டு டாஷ்போர்டு திறக்கும்.' },
  { caption: 'Sri Murugan Stores வெற்றிகரமாக உருவாக்கப்பட்டது.', narration: 'ஸ்ரீ முருகன் ஸ்டோர்ஸ் கடை வெற்றிகரமாக உருவாக்கப்பட்டுள்ளது. அடுத்த வீடியோவில் முகவரி, ஜி எஸ் டி, யூ பி ஐ, பில் எண் மற்றும் பிரிண்டர் அமைப்புகளை முழுமையாக செய்வோம்.' },
]

const narration = segments.map(item => item.narration).join(' ')
const audioPath = path.join(workDir, 'narration.mp3')
const voice = spawnSync('py', ['-m', 'edge_tts', '--voice', 'ta-IN-PallaviNeural', '--rate=-6%', '--text', narration, '--write-media', audioPath], { stdio: 'inherit' })
if (voice.status !== 0 || !existsSync(audioPath)) throw new Error('Tamil narration generation failed.')
const probe = spawnSync('ffprobe', ['-v', 'error', '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', audioPath], { encoding: 'utf8' })
const audioDurationMs = Math.round(Number.parseFloat(probe.stdout.trim()) * 1000)
const totalChars = segments.reduce((sum, item) => sum + item.narration.length, 0)
const durations = segments.map(item => Math.max(5_000, Math.round(audioDurationMs * item.narration.length / totalChars + 650)))

const browser = await chromium.launch({ headless: true, ...(process.platform === 'win32' ? { channel: 'msedge' } : {}) })
const context = await browser.newContext({ viewport: { width: 1280, height: 720 }, recordVideo: { dir: workDir, size: { width: 1280, height: 720 } } })
const page = await context.newPage()

async function caption(text) {
  await page.evaluate(value => {
    let box = document.getElementById('demo-caption')
    if (!box) {
      box = document.createElement('div'); box.id = 'demo-caption'
      Object.assign(box.style, { position:'fixed',zIndex:'2147483647',left:'50%',bottom:'22px',transform:'translateX(-50%)',width:'min(960px,calc(100% - 48px))',padding:'14px 22px',borderRadius:'16px',background:'rgba(8,15,32,.92)',color:'#fff',border:'1px solid rgba(255,255,255,.25)',boxShadow:'0 18px 50px rgba(0,0,0,.3)',font:'700 21px/1.4 Nirmala UI,Segoe UI,sans-serif',textAlign:'center' })
      document.body.appendChild(box)
    }
    box.textContent = value
  }, text)
}
async function point(locator) {
  const box = await locator.boundingBox(); if (!box) return
  await page.evaluate(({x,y}) => {
    let dot=document.getElementById('demo-pointer'); if(!dot){dot=document.createElement('div');dot.id='demo-pointer';Object.assign(dot.style,{position:'fixed',zIndex:'2147483646',width:'24px',height:'24px',borderRadius:'50%',background:'rgba(79,70,229,.28)',border:'3px solid #4f46e5',boxShadow:'0 0 0 8px rgba(79,70,229,.12)',pointerEvents:'none',transition:'left .45s ease,top .45s ease'});document.body.appendChild(dot)} dot.style.left=`${x-12}px`;dot.style.top=`${y-12}px`
  }, { x: box.x + box.width / 2, y: box.y + box.height / 2 })
  await page.waitForTimeout(550)
}
async function fill(locator, value) { await point(locator); await locator.fill(value) }
async function click(locator) { await point(locator); await locator.click() }

await page.goto(`${baseURL}/register`, { waitUntil: 'networkidle' })
await caption(segments[0].caption); await page.waitForTimeout(durations[0])
await caption(segments[1].caption)
await fill(page.locator('input[autocomplete="name"]'), demo.owner)
await fill(page.locator('input[type="email"]'), demo.email)
await fill(page.locator('input[autocomplete="tel"]'), demo.mobile)
await page.waitForTimeout(durations[1])
await caption(segments[2].caption)
await fill(page.locator('input[autocomplete="new-password"]').first(), demo.password)
await fill(page.locator('input[autocomplete="new-password"]').last(), demo.password)
await click(page.getByRole('button', { name: 'Continue' }))
await page.waitForTimeout(durations[2])
await caption(segments[3].caption)
await fill(page.getByPlaceholder('e.g. Sharma Electronics'), demo.business)
await page.waitForTimeout(durations[3])
await caption(segments[4].caption)
await point(page.locator('select').nth(0)); await page.locator('select').nth(0).selectOption('proprietorship')
await point(page.locator('select').nth(1)); await page.locator('select').nth(1).selectOption({ label: demo.state })
await page.waitForTimeout(durations[4])
await caption(segments[5].caption)
await fill(page.getByPlaceholder(/29AABCU9603R1Z6/i), demo.gstin)
await page.waitForTimeout(durations[5])
await caption(segments[6].caption)
await click(page.getByRole('button', { name: 'Create Free Account' }))
await page.waitForURL(`${baseURL}/`, { timeout: 20_000 })
await page.getByRole('heading', { name: 'Dashboard', exact: true }).waitFor({ timeout: 12_000 })
await page.waitForTimeout(durations[6])
await caption(segments[7].caption)
await page.waitForTimeout(durations[7])

await context.storageState({ path: authPath })
writeFileSync(storePath, JSON.stringify(demo, null, 2))
const video = page.video(); await context.close(); await browser.close(); const rawVideo = await video.path()
const finalPath = path.join(outputDir, '01-create-store-tamil.mp4')
const encode = spawnSync('ffmpeg', ['-y','-i',rawVideo,'-i',audioPath,'-c:v','libx264','-preset','medium','-crf','20','-pix_fmt','yuv420p','-c:a','aac','-b:a','128k','-shortest','-movflags','+faststart',finalPath], { stdio:'inherit' })
if (encode.status !== 0 || !existsSync(finalPath)) throw new Error('Tamil setup MP4 creation failed.')
rmSync(workDir,{recursive:true,force:true})
console.log(`Created ${finalPath}`)

