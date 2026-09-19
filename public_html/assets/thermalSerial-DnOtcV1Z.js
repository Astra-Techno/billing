function e(){return window.isSecureContext&&`serial`in navigator}function t(e){let t=atob(e);return Uint8Array.from(t,e=>e.charCodeAt(0))}function n(){return new TextEncoder().encode(`\x1B@\x1BaAI Billing
SC588 printer ready


`)}async function r(e){let t=await navigator.serial.requestPort(),n=!1;try{let r=typeof e==`function`?await e():e;await t.open({baudRate:9600}),n=!0;let i=t.writable.getWriter();try{await i.write(r)}finally{i.releaseLock()}}finally{n&&await t.close()}}export{n as i,t as n,r,e as t};