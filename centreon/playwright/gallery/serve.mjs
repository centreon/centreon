// Serves the static gallery build (rspack.config.gallery.build.js).
import { createReadStream } from 'node:fs';
import { stat } from 'node:fs/promises';
import { createServer } from 'node:http';
import { extname, join, normalize, resolve } from 'node:path';

const root = resolve('www/static/playwright-gallery-build');
const port = Number(process.env.GALLERY_PORT ?? 9093);
const contentTypes = {
  '.css': 'text/css',
  '.html': 'text/html',
  '.js': 'text/javascript',
  '.json': 'application/json',
  '.png': 'image/png',
  '.svg': 'image/svg+xml',
  '.ttf': 'font/ttf',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2'
};

createServer(async (request, response) => {
  const [pathname] = (request.url ?? '/').split('?');
  const file = join(root, normalize(decodeURIComponent(pathname)));
  const found = file.startsWith(root)
    ? await stat(file).catch(() => null)
    : null;

  if (!found?.isFile()) {
    response.writeHead(404).end();

    return;
  }
  response.writeHead(200, {
    // One build per run: let the browser cache it across mounts.
    'Cache-Control': 'max-age=3600',
    'Content-Type': contentTypes[extname(file)] ?? 'application/octet-stream'
  });
  createReadStream(file).pipe(response);
}).listen(port, 'localhost');
