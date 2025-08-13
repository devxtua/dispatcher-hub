import createApp from '@shopify/app-bridge';
import { getSessionToken } from '@shopify/app-bridge-utils';

function sameOrigin(url) {
  try {
    const u = new URL(url, window.location.origin);
    return u.origin === window.location.origin;
  } catch {
    return true; // относительный URL — это наш домен
  }
}

export function patchFetchForShopify() {
  if (window.__shopifyFetchPatched__) return;
  window.__shopifyFetchPatched__ = true;

  // 1) пытаемся взять host из ?host=..., и ПРИ ЭТОМ кладём его в sessionStorage
  const params = new URLSearchParams(window.location.search);
  const hostFromQuery = params.get('host');
  if (hostFromQuery) sessionStorage.setItem('shopify:host', hostFromQuery);

  // 2) берём host либо из query, либо из sessionStorage (после SPA-переходов)
  const host = hostFromQuery || sessionStorage.getItem('shopify:host');
  const apiKey = import.meta.env.VITE_SHOPIFY_API_KEY;

  if (!host || !apiKey) return; // вне админки Shopify — ничего не делаем

  const app = createApp({ apiKey, host, forceRedirect: true });
  const originalFetch = window.fetch.bind(window);

  window.fetch = async (input, init = {}) => {
    const url = typeof input === 'string' ? input : input.url;
    if (!sameOrigin(url)) return originalFetch(input, init);

    let token;
    try {
      token = await getSessionToken(app);
    } catch {
      return originalFetch(input, init); //fallback для обычного веба
    }

    const headers = new Headers(init.headers || {});
    headers.set('Authorization', `Bearer ${token}`);
    if (!headers.has('X-Requested-With')) headers.set('X-Requested-With', 'XMLHttpRequest');

    const newInit = { ...init, headers, credentials: 'omit' };

    if (typeof Request !== 'undefined' && input instanceof Request) {
      return originalFetch(new Request(input, newInit));
    }
    return originalFetch(url, newInit);
  };
}
