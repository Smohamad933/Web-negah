// Optional development preview when native PHP isn't installed. Not used on IIS.
import http from 'node:http';
import fs from 'node:fs';
import {fileURLToPath} from 'node:url';
import {PHP, PHPRequestHandler, FileLockManagerInMemory} from '@php-wasm/universal';
import {loadNodeRuntime, createNodeFsMountHandler} from '@php-wasm/node';
const root=fileURLToPath(new URL('..',import.meta.url)).replace(/\/$/,'');
const php=new PHP(await loadNodeRuntime('8.5',{fileLockManager:new FileLockManagerInMemory(),emscriptenOptions:{processId:1}}));
await php.mount('/www',createNodeFsMountHandler(root));
// Allow multi-image submissions; each individual image is still limited to 5 MB in PHP.
php.writeFile('/internal/shared/php.ini','upload_max_filesize=5M\npost_max_size=50M\nmax_file_uploads=12\n');
const handler=new PHPRequestHandler({php,documentRoot:'/www',absoluteUrl:'http://localhost:8080',cookieStore:false});
let queue=Promise.resolve();
const types={css:'text/css',js:'text/javascript',svg:'image/svg+xml',jpg:'image/jpeg',jpeg:'image/jpeg',png:'image/png',webp:'image/webp',woff:'font/woff',woff2:'font/woff2',ttf:'font/ttf',txt:'text/plain'};
http.createServer(async(req,res)=>{
 try{
  const path=decodeURIComponent(new URL(req.url,'http://localhost').pathname);
  if(/^\/(assets|uploads)\/[a-zA-Z0-9._-]+\.(css|js|svg|jpg|jpeg|png|webp|woff2?|ttf|txt)$/.test(path)&&fs.existsSync(root+path)){
   if(!['GET','HEAD'].includes(req.method)){res.writeHead(405);res.end();return;}
   res.setHeader('Content-Type',types[path.split('.').pop()]);res.setHeader('X-Content-Type-Options','nosniff');
   if(req.method==='HEAD'){res.end();return;}fs.createReadStream(root+path).pipe(res);return;
  }
  if(!['/','/index.php','/admin.php','/works.php','/project.php'].includes(path)){res.writeHead(404);res.end('Not found');return;}
  const chunks=[];let size=0;for await(const chunk of req){size+=chunk.length;if(size>52*1024*1024){res.writeHead(413);res.end();return;}chunks.push(chunk);}
  const result=queue.then(()=>handler.request({url:req.url,method:req.method,headers:req.headers,body:Buffer.concat(chunks)}));queue=result.catch(()=>{});
  const response=await result;
  for(const [key,value]of Object.entries(response.headers))res.setHeader(key,value);
  res.writeHead(response.httpStatusCode);res.end(response.bytes);if(response.errors)console.error(response.errors);
 }catch(error){console.error(error);if(!res.headersSent)res.writeHead(500);res.end('Preview error');}
}).listen(8080,'0.0.0.0',()=>console.log('Negah Media PHP preview on 0.0.0.0:8080'));
