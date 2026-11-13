const CACHE='arkusz-1.1.0';
const SHELL=['/','/index.html','/style.css','/themes.css','/themes.js','/app.js','/dictionaries.js','/order-editor.js','/icon.svg','/manifest.webmanifest'];
self.addEventListener('install',event=>{event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(SHELL)));self.skipWaiting();});
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim()));});
self.addEventListener('fetch',event=>{const url=new URL(event.request.url);if(event.request.method!=='GET'||url.origin!==self.location.origin||url.pathname==='/api.php')return;event.respondWith(fetch(event.request).catch(()=>caches.match(event.request).then(r=>r||(event.request.mode==='navigate'?caches.match('/index.html'):Response.error()))));});
