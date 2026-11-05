# SEO-tjek af et site: henter sitemap (sample), tjekker hver side og skriver en rapport (Markdown) til stdout.
import sys, re, json, urllib.request, urllib.parse, html, collections, time, gzip, ssl
BASE = sys.argv[1].rstrip('/'); MAX = int(sys.argv[2]) if len(sys.argv) > 2 else 160
ctx = ssl.create_default_context()
def get(url, timeout=40):
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36 PlayformAudit', 'Accept-Encoding': 'gzip'})
    time.sleep(0.8)  # skaansomt mod SiteGround
    t = time.time()
    try:
        with urllib.request.urlopen(req, timeout=timeout, context=ctx) as r:
            raw = r.read(); 
            if r.headers.get('Content-Encoding') == 'gzip': raw = gzip.decompress(raw)
            return r.status, r.geturl(), raw.decode('utf-8', 'replace'), time.time() - t, dict(r.headers)
    except urllib.error.HTTPError as e:
        return e.code, url, '', time.time() - t, {}
    except Exception as e:
        return 0, url, str(e), time.time() - t, {}
def locs(xml): return re.findall(r'<loc>\s*([^<]+?)\s*</loc>', xml)
# Sitemap
st, _, xml, _, _ = get(BASE + '/wp-sitemap.xml')
urls = []; maps = []
if st == 200:
    maps = locs(xml)
    for m in maps:
        s2, _, x2, _, _ = get(m)
        if s2 == 200:
            l = locs(x2); kind = re.sub(r'.*wp-sitemap-', '', m)
            # sample: alle sider/kategorier/indlæg, men højst 40 produkter pr. sitemap
            if 'product-' in kind and 'product_cat' not in kind: l = l[:40]
            urls += l
else:
    urls = [BASE + '/']
seen = set(); urls = [u for u in urls if not (u in seen or seen.add(u))][:MAX]
if BASE + '/' not in urls: urls.insert(0, BASE + '/')
rows = []; titles = collections.Counter(); descs = collections.Counter(); internal = set(); problems = collections.Counter()
for u in urls:
    st, final, body, dt, hd = get(u)
    r = {'url': u, 'status': st, 'ms': int(dt*1000), 'redirect': final if final.rstrip('/') != u.rstrip('/') else ''}
    if st == 200 and body:
        m = re.search(r'<title[^>]*>(.*?)</title>', body, re.S|re.I); r['title'] = html.unescape(m.group(1).strip()) if m else ''
        m = re.search(r'<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)', body, re.I); r['desc'] = html.unescape(m.group(1)) if m else ''
        m = re.search(r'<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)', body, re.I); r['canonical'] = m.group(1) if m else ''
        m = re.search(r'<meta[^>]+name=["\']robots["\'][^>]+content=["\']([^"\']*)', body, re.I); r['robots'] = m.group(1) if m else ''
        r['h1'] = len(re.findall(r'<h1[\s>]', body, re.I))
        r['h1text'] = html.unescape(re.sub(r'<[^>]+>', '', (re.search(r'<h1[^>]*>(.*?)</h1>', body, re.S|re.I) or [None,''])[1] if re.search(r'<h1[^>]*>(.*?)</h1>', body, re.S|re.I) else '')).strip()[:80]
        r['og'] = bool(re.search(r'property=["\']og:title', body))
        r['ld'] = sorted(set(re.findall(r'"@type"\s*:\s*"([A-Za-z]+)"', body)))
        imgs = re.findall(r'<img\b[^>]*>', body, re.I); r['img'] = len(imgs); r['img_noalt'] = sum(1 for i in imgs if not re.search(r'\balt=', i, re.I))
        r['words'] = len(re.findall(r'\w+', re.sub(r'<script.*?</script>|<style.*?</style>|<[^>]+>', ' ', body, flags=re.S)))
        r['bytes'] = len(body)
        r['test'] = len(re.findall(r'\btest(?:vinder|et|en)?\b', re.sub(r'<script.*?</script>|<[^>]+>', ' ', body, flags=re.S), re.I))
        for href in re.findall(r'href=["\']([^"\'#?]+)', body):
            if href.startswith('/') and not href.startswith('//'): internal.add(BASE + href)
            elif href.startswith(BASE): internal.add(href.split('?')[0])
        titles[r['title']] += 1; descs[r['desc']] += 1
        if not r['title']: problems['Mangler <title>'] += 1
        if len(r['title']) > 65: problems['Titel over 65 tegn'] += 1
        if not r['desc']: problems['Mangler metabeskrivelse'] += 1
        elif len(r['desc']) > 165: problems['Metabeskrivelse over 165 tegn'] += 1
        elif len(r['desc']) < 70: problems['Metabeskrivelse under 70 tegn'] += 1
        if r['h1'] != 1: problems[f"H1-antal != 1"] += 1
        if not r['canonical']: problems['Mangler canonical'] += 1
        elif r['canonical'].rstrip('/') != u.rstrip('/'): problems['Canonical peger et andet sted hen'] += 1
        if 'noindex' in r['robots']: problems['noindex i sitemap-URL'] += 1
        if r['img_noalt']: problems['Billeder uden alt'] += 1
        if r['words'] < 150: problems['Tynd side (<150 ord)'] += 1
        if r['ms'] > 2500: problems['Langsom (>2,5 s)'] += 1
        if r['test']: problems['Ordet "test" på siden'] += 1
    else:
        problems[f'Status {st}'] += 1
    rows.append(r)
# Brudte interne links (stikprøve)
broken = []
sample = sorted(internal - set(urls))[:120]
for u in sample:
    st, final, _, _, _ = get(u, 25)
    if st in (0, 404, 410, 500, 502, 503): broken.append((u, st))
    elif st in (301, 302) or (final and final.rstrip('/') != u.rstrip('/')): broken.append((u, f'→ {final}'))
dupt = {t: n for t, n in titles.items() if n > 1 and t}; dupd = {d: n for d, n in descs.items() if n > 1 and d}
print(f"# SEO-tjek {BASE}\n")
print(f"Sitemap: {'ok, ' + str(len(maps)) + ' delsitemaps' if maps else 'IKKE FUNDET (' + str(st) + ')'}; tjekket {len(rows)} sider, {len(sample)} interne links.\n")
print("## Problemer (antal sider)\n")
for k, v in problems.most_common(): print(f"- {k}: {v}")
if not problems: print("- ingen")
print(f"\n## Dublerede titler ({len(dupt)})\n"); [print(f"- {n}× {t}") for t, n in list(dupt.items())[:15]]
print(f"\n## Dublerede metabeskrivelser ({len(dupd)})\n"); [print(f"- {n}× {d[:90]}…") for d, n in list(dupd.items())[:15]]
print(f"\n## Brudte/omdirigerede interne links ({len(broken)})\n"); [print(f"- {u} {s}") for u, s in broken[:40]]
print("\n## Sider\n\n| Status | ms | H1 | ord | img/u.alt | title (len) | desc len | canonical | robots | JSON-LD | url |\n|---|---|---|---|---|---|---|---|---|---|---|")
for r in rows:
    print(f"| {r['status']} | {r['ms']} | {r.get('h1','')} | {r.get('words','')} | {r.get('img','')}/{r.get('img_noalt','')} | {r.get('title','')[:60]} ({len(r.get('title',''))}) | {len(r.get('desc',''))} | {'ok' if r.get('canonical','').rstrip('/')==r['url'].rstrip('/') else r.get('canonical','–')[:40]} | {r.get('robots','')} | {','.join(r.get('ld',[]))} | {r['url'].replace(BASE,'')} |")
