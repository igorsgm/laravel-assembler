import fs from 'fs';
import { defineConfig, loadEnv } from 'vite';
import { homedir } from 'os';
import { resolve } from 'path';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ command, mode }) => {
  process.env = { ...process.env, ...loadEnv(mode, process.cwd()) };

  return {
    plugins: [
      laravel({
        input: ['resources/css/app.css', 'resources/js/app.js'],
        refresh: true,
      }),
    ],
    server: detectServerConfig(),
  };
});

function detectServerConfig () {
  const host = new URL(process.env.VITE_APP_URL).hostname;
  let keyPath = resolve(homedir(), `.config/valet/Certificates/${host}.key`)
  let certificatePath = resolve(homedir(),
    `.config/valet/Certificates/${host}.crt`)

  if (!fs.existsSync(keyPath)) {
    return {};
  }

  if (!fs.existsSync(certificatePath)) {
    return {};
  }

  return {
    hmr: { host },
    host,
    https: {
      key: fs.readFileSync(keyPath),
      cert: fs.readFileSync(certificatePath),
    },
  }
}
