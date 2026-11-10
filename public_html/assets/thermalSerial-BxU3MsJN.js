function e(){return window.isSecureContext&&!/Android|iPhone|iPad|iPod/i.test(navigator.userAgent)&&`serial`in navigator}function t(e){let t=atob(e);return Uint8Array.from(t,e=>e.charCodeAt(0))}function n(){return new TextEncoder().encode(`\x1B@\x1BaAI Billing
SC588 printer ready


`)}async function r(e){let t=await navigator.serial.getPorts(),n=t.length>0?t[0]:await navigator.serial.requestPort(),r=!1;try{let t=typeof e==`function`?await e():e;await n.open({baudRate:9600}),r=!0;let i=n.writable.getWriter();try{await i.write(t)}finally{i.releaseLock()}}finally{r&&await n.close()}}export{n as i,t as n,r,e as t};