import { spawn, execFile } from 'node:child_process'
import { mkdir, readFile, writeFile, unlink, copyFile } from 'node:fs/promises'
import { promisify } from 'node:util'
import path from 'node:path'
import assert from 'node:assert/strict'
import { generateKeyPairSync, sign } from 'node:crypto'
import { chromium } from '@playwright/test'

const home = path.resolve(`desktop/.test-data-${Date.now()}/PC Data`)
const base = 'http://127.0.0.1:19765'
const app = path.resolve(process.env.BILLING_OFFLINE_PACKAGE ? path.join(process.env.BILLING_OFFLINE_PACKAGE,'desktop') : 'desktop')
const phpRoot = process.env.BILLING_OFFLINE_PACKAGE ? path.join(app,'runtime/php') : 'C:/laragon1/bin/php/php-8.3.16-Win32-vs16-x64'
const mysqlRoot = process.env.BILLING_OFFLINE_PACKAGE ? path.join(app,'runtime/mysql') : 'C:/laragon1/bin/mysql/mysql-8.4.3-winx64'
await mkdir(home, { recursive: true })
const { publicKey, privateKey } = generateKeyPairSync('rsa',{modulusLength:3072})
const jwk = publicKey.export({format:'jwk'})
const toB64 = value => Buffer.from(value.replace(/-/g,'+').replace(/_/g,'/') + '='.repeat((4-value.length%4)%4),'base64').toString('base64')
const testPublicKey = `<RSAKeyValue><Modulus>${toB64(jwk.n)}</Modulus><Exponent>${toB64(jwk.e)}</Exponent></RSAKeyValue>`
const keyFile = path.join(home,'test-public-key.xml'), testHost = path.join(home,'LicenseHost.exe'), appHost = path.join(app,'LicenseHost.exe')
await writeFile(keyFile,testPublicKey)
await promisify(execFile)(`${process.env.WINDIR}/Microsoft.NET/Framework64/v4.0.30319/csc.exe`,['/nologo','/target:exe','/platform:x64',`/out:${testHost}`,'/reference:System.Management.dll','/reference:System.Security.dll','/reference:System.Web.Extensions.dll',`/resource:${keyFile},license-public-key.xml`,path.resolve('desktop/LicenseHost.cs')])
let originalHost = null
try { originalHost = await readFile(appHost) } catch {}
await copyFile(testHost,appHost)
let launcher, output = ''
function start() {
  launcher = spawn('powershell.exe', ['-NoProfile','-ExecutionPolicy','Bypass','-File',path.join(app,'Launch.ps1'),'-Headless','-DataRoot',home,'-AppPort','19765','-DatabasePort','19766','-PhpRoot',phpRoot,'-MysqlRoot',mysqlRoot], { stdio: ['ignore','pipe','pipe'] })
  launcher.stdout.on('data', b => { output += b; process.stdout.write(b) }); launcher.stderr.on('data', b => { output += b; process.stderr.write(b) })
}
async function waitReady() {
  for (let i=0; i<180; i++) {
    if (launcher.exitCode !== null) throw new Error(`Launcher exited: ${output}`)
    try { const r = await fetch(base+'/desktop-info'); if (r.ok) return } catch {}
    await new Promise(r=>setTimeout(r,500))
  }
  throw new Error(`Startup timed out: ${output}`)
}
async function stop() {
  await writeFile(path.join(home,'stop'),'stop')
  if (launcher.exitCode !== null) return
  await Promise.race([new Promise(r=>launcher.once('exit',r)),new Promise(r=>setTimeout(r,20000))])
  if (launcher.exitCode === null) { launcher.kill(); throw new Error('Graceful shutdown timed out.') }
}
start()
let token, businessId, browser
async function request(route, body, expected = 200) {
  const response = await fetch(base + '/api/' + route, {
    method: body === undefined ? 'GET' : 'POST',
    headers: { Origin: base, ...(token ? { Authorization: `Bearer ${token}`, 'X-Business-ID': String(businessId) } : {}), ...(body instanceof FormData ? {} : { 'Content-Type':'application/json' }) },
    body: body === undefined ? undefined : body instanceof FormData ? body : JSON.stringify(body),
  })
  assert.equal(response.status, expected, `${route}: ${await response.clone().text()}`)
  return response
}
async function command(name, method, body) { return (await (await request(`task/${name}/${method}`, body)).json()).data }
const credentials = { email:'offline-test@example.test', password:'OfflineTest123!' }
async function login() {
  const session = (await (await request('login', credentials)).json()).data
  token = session.token; businessId = session.business_id; return session
}
async function installTestLicense() {
  const device = JSON.parse((await promisify(execFile)(appHost,['device'])).stdout)
  const claims = {license_id:'offline-integration-test',device_id:device.device_id,customer:{email:credentials.email},company:{name:'Offline Test Shop'},edition:'offline-single-pc',status:'active',issued_at:Math.floor(Date.now()/1000),expires_at:null,max_version:null}
  const payload = Buffer.from(JSON.stringify(claims)), signature = sign('sha256',payload,privateKey)
  const document = JSON.stringify({payload:payload.toString('base64url'),signature:signature.toString('base64url')})
  const responseFile=path.join(home,'test-license-response.json')
  await writeFile(responseFile,JSON.stringify({data:{license_id:claims.license_id,license_document:document,public_key:testPublicKey}}))
  await promisify(execFile)(appHost,['install',responseFile,home])
}
try {
  await waitReady()
  const migrationCheck = await promisify(execFile)(phpRoot + '/php.exe', ['-c',path.join(app,'php.ini'),'-d',`extension_dir=${path.join(phpRoot,'ext')}`,path.resolve('desktop/test-cloud-migration.php'),home], { env:{...process.env,OPENSSL_CONF:path.join(phpRoot,'extras/ssl/openssl.cnf')} })
  assert.match(migrationCheck.stdout, /desktop exclusion verified/)
  assert.equal((await (await fetch(base+'/desktop-info')).json()).needs_setup, true)
  browser = await chromium.launch({ headless: true })
  const context = await browser.newContext()
  await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort())
  const page = await context.newPage()
  const pageErrors = []; page.on('pageerror',error=>pageErrors.push(error.message))
  await page.goto(base); await page.waitForURL('**/register')
  await page.getByRole('link',{name:'Sign in',exact:true}).click()
  await page.waitForURL('**/login')
  await page.getByRole('heading',{name:'Welcome back'}).waitFor()
  await page.getByRole('link',{name:'Set up this PC',exact:true}).first().click()
  await page.waitForURL('**/register')
  const session = (await (await request('register', { ...credentials, password_confirmation:credentials.password, name:'Offline Owner', mobile:'9876543210', business_name:'Offline Test Shop', business_type:'proprietorship', state_id:26 })).json()).data
  token = session.token; businessId = session.business_id
  await request('all/Client',undefined,402)
  await installTestLicense()
  await page.getByRole('link',{name:'Sign in',exact:true}).click()
  await page.waitForURL('**/login')
  await page.locator('input[type=email]').fill(credentials.email)
  await page.locator('input[autocomplete=current-password]').fill(credentials.password)
  await page.locator('#login-form button[type=submit]').click()
  await page.waitForURL(base+'/')
  // Upgrade compatibility: early desktop builds stored a business without its owner
  // role, which reduced navigation to Dashboard and Invoices and blocked Settings.
  await page.evaluate(() => {
    const businesses = JSON.parse(localStorage.getItem('businesses') || '[]')
    localStorage.setItem('businesses', JSON.stringify(businesses.map(({ role, permissions, ...business }) => business)))
  })
  await page.reload()
  for (const menu of ['Clients','Quotes','Expenses','Products','Reports','Settings']) {
    await page.getByRole('link',{name:menu,exact:true}).waitFor()
  }
  await page.goto(base+'/settings')
  await page.getByRole('heading',{name:'Settings',exact:true}).waitFor()
  await page.getByRole('button',{name:'My Business',exact:true}).waitFor()
  await page.goto(base+'/')
  await request('register', { ...credentials }, 403)
  const customer = await command('Client','create',{ name:'Offline Customer', type:'individual', state_id:26 })
  const clientId = customer.client_id
  const invoice = await command('Invoice','create',{ client_id:clientId, issue_date:'2026-09-14', due_date:'2026-09-30', discount_type:'percent', discount_value:10, items:[{ description:'Offline item', quantity:2, unit_price:100, gst_rate:18 }] })
  assert.equal(Number(invoice.total),212)
  const lines = (await (await request(`list/Invoice:items?invoice_id=${invoice.invoice_id}`)).json()).data
  assert.equal(Number(lines[0].taxable_amt),180); assert.equal(Number(lines[0].cgst_amt)+Number(lines[0].sgst_amt),32.4)
  await command('Payment','record',{ invoice_id:invoice.invoice_id, amount:50, method:'cash', payment_date:'2026-09-14' })
  const logo = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aS1cAAAAASUVORK5CYII='
  const uploaded = await command('Business','uploadLogo',{ logo })
  assert.equal((await fetch(base+uploaded.logo)).status,200)
  const backup = await command('Desktop','backup',{})
  const bytes = await (await request('task/Desktop/download',{ name:backup.name })).arrayBuffer()
  assert(bytes.byteLength > 1000)
  await command('Client','create',{ name:'Created After Backup',type:'individual' })
  const before = await readFile(path.join(home,'state.json'),'utf8')
  const invalid = new FormData(); invalid.append('confirmation','RESTORE'); invalid.append('backup',new Blob(['invalid archive']),'invalid.aibackup')
  await request('task/Desktop/restore',invalid,422)
  assert.equal(await readFile(path.join(home,'state.json'),'utf8'),before)
  for (const mode of ['path','checksum','sql','scope']) {
    const changed = path.join(home,`${mode}.aibackup`)
    await promisify(execFile)(path.join(phpRoot,'php.exe'),['-c',path.join(app,'php.ini'),'-d',`extension_dir=${path.join(phpRoot,'ext')}`,'desktop/test-archive.php',path.join(home,'backups',backup.name),changed,mode])
    const form = new FormData(); form.append('confirmation','RESTORE'); form.append('backup',new Blob([await readFile(changed)]),`${mode}.aibackup`)
    await request('task/Desktop/restore',form,['path','checksum'].includes(mode)?422:500)
    assert.equal(await readFile(path.join(home,'state.json'),'utf8'),before)
    assert.equal((await (await request('all/Client')).json()).data.length,2)
  }
  const restore = new FormData(); restore.append('confirmation','RESTORE'); restore.append('backup',new Blob([bytes]),backup.name)
  await request('task/Desktop/restore',restore)
  await request('all/Client',undefined,401)
  const restoredSession = await login()
  const clients = (await (await request('all/Client')).json()).data
  assert.equal(clients.length,1); assert.equal(clients[0].name,'Offline Customer')
  assert.equal((await (await request('all/Client?business_id=99999')).json()).data.length,1,'Query inputs must not replace authenticated business scope')
  const inv = (await (await request(`item/Invoice?id=${invoice.invoice_id}`)).json()).data
  assert.equal(Number(inv.amount_paid),50); assert.equal(Number(inv.amount_due),162)
  assert.equal((await fetch(base+uploaded.logo)).status,200)
  const pdf = await request(`invoice/${invoice.invoice_id}/pdf`)
  assert((await pdf.text()).startsWith('%PDF-'))
  await request('run-migrate',undefined,404)
  await request('guest-task/Invoice/updateOverdue',{},404)
  const badOrigin = await fetch(base+'/api/task/Desktop/backup',{method:'POST',headers:{Origin:'https://example.com'}})
  assert.equal(badOrigin.status,403)
  await page.evaluate(session => { localStorage.setItem('token',session.token);localStorage.setItem('user',JSON.stringify(session.user));localStorage.setItem('business_id',String(session.business_id));localStorage.setItem('businesses',JSON.stringify(session.businesses)) },restoredSession)
  await page.goto(base+'/offline-backups')
  await page.getByRole('main').getByRole('heading',{name:'Backup & Restore',exact:true}).waitFor()
  await page.getByRole('button',{name:'Create & download backup'}).waitFor()
  await page.locator('li').filter({hasText:backup.name}).waitFor()
  await page.screenshot({path:'dist/offline-backups.png',fullPage:true})
  const downloadEvent = page.waitForEvent('download')
  await page.getByRole('button',{name:'Create & download backup'}).click()
  const download = await downloadEvent; const usbFile = path.join(home,'usb-copy.aibackup'); await download.saveAs(usbFile)
  await command('Client','create',{name:'After UI Backup',type:'individual'})
  await page.locator('input[type=file]').setInputFiles(usbFile)
  await page.getByLabel('Type RESTORE to confirm').fill('RESTORE')
  await page.getByRole('button',{name:'Restore selected backup'}).click()
  await page.waitForURL('**/login?restored=1')
  await login(); assert.equal((await (await request('all/Client')).json()).data.length,1)
  const afterUiRestore = await login()
  await page.evaluate(session=>{ localStorage.setItem('token',session.token);localStorage.setItem('user',JSON.stringify(session.user));localStorage.setItem('business_id',String(session.business_id));localStorage.setItem('businesses',JSON.stringify(session.businesses)) },afterUiRestore)
  await page.goto(base+`/print/invoice/${invoice.invoice_id}`)
  await page.getByText('Offline item',{exact:true}).first().waitFor()
  await page.goto(base+`/print/invoice/${invoice.invoice_id}?paper=thermal58`)
  await page.locator('.receipt-doc').waitFor({timeout:10000}).catch(async e => { throw new Error(`${e.message}\nPrint page: ${await page.locator('body').innerText()}`) })
  assert.equal(await page.locator('.receipt-item').count(),1)
  assert.match(await page.locator('.receipt-doc').innerText(),/Offline item/)
  const receiptWidth = await page.evaluate(() => {
    const page = document.querySelector('.print-page')
    const receipt = document.querySelector('.receipt-doc')
    return { page: page.getBoundingClientRect().width, content: receipt.getBoundingClientRect().width, overflow: receipt.scrollWidth > receipt.clientWidth }
  })
  assert(receiptWidth.page >= 210 && receiptWidth.page <= 230,`58mm page width: ${receiptWidth.page}`)
  assert(receiptWidth.content < receiptWidth.page && !receiptWidth.overflow,'Receipt must fit inside 58mm paper')
  await stop(); await unlink(path.join(home,'stop')); start(); await waitReady(); await login()
  assert.equal((await (await request('all/Client')).json()).data.length,1)
  assert.equal(Number((await (await request(`item/Invoice?id=${invoice.invoice_id}`)).json()).data.amount_paid),50)
  assert.deepEqual(pageErrors,[],'Browser must not have uncaught application errors')
  console.log('PASS: local onboarding, discounted billing, payment, uploads, UI backup/download/restore, archive path/checksum/SQL failure safety, restricted SQL import, session revocation, PDF, offline browser, restart persistence and access restrictions.')
} finally {
  await browser?.close()
  await stop()
  if (originalHost) await writeFile(appHost,originalHost); else await unlink(appHost).catch(()=>{})
}
