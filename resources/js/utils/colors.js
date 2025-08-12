export const hexSteps = ['00','33','66','99','CC','FF'];
export const webSafePalette = hexSteps.flatMap(r =>
  hexSteps.flatMap(g => hexSteps.map(b => `#${r}${g}${b}`))
);

export function normalizeHex(v) {
  if (!v) return '#000000';
  v = v.trim().toUpperCase();
  if (!v.startsWith('#')) v = '#' + v;
  const m3 = /^#([0-9A-F]{3})$/.exec(v);
  if (m3) v = '#' + m3[1].split('').map(s => s + s).join('');
  return v;
}
