/* Kitzhen (samme tema som Fitbase) – lidt JavaScript til sliders, menu, sortering og foldbar tekst. Siden virker også uden. */
(function () {
	// Sliders: pilene scroller én kortbredde ad gangen
	document.querySelectorAll('[data-slider-arrows]').forEach(function (wrap) {
		var slider = document.getElementById(wrap.getAttribute('data-slider-arrows'));
		if (!slider) return;
		var buttons = wrap.querySelectorAll('button');
		function update() {
			buttons[0].disabled = slider.scrollLeft <= 2;
			buttons[1].disabled = slider.scrollLeft + slider.clientWidth >= slider.scrollWidth - 2;
		}
		buttons.forEach(function (b) {
			b.addEventListener('click', function () {
				var item = slider.querySelector('.slider__item');
				var step = item ? item.getBoundingClientRect().width + 16 : slider.clientWidth / 4;
				slider.scrollBy({ left: step * parseInt(b.getAttribute('data-dir'), 10), behavior: 'smooth' });
			});
		});
		slider.addEventListener('scroll', update, { passive: true });
		update();
	});

	var header = document.querySelector('.site-header');
	var menu = document.getElementById('hovedmenu');
	var mobile = window.matchMedia('(max-width: 1080px)');

	// Menupanel: kategorien man holder over (eller fokuserer) styrer midter- og priskolonnen.
	// En lille forsinkelse gør, at man kan bevæge musen skråt over til midterkolonnen uden at skifte.
	function showPane(inner, idx) {
		inner.querySelectorAll('[data-pane]').forEach(function (el) {
			el.classList.toggle('is-active', el.getAttribute('data-pane') === idx);
		});
	}
	document.querySelectorAll('.mm__cat').forEach(function (cat) {
		var timer;
		var inner = cat.closest('.mm__inner');
		cat.addEventListener('mouseenter', function () {
			if (mobile.matches) return;
			clearTimeout(timer);
			timer = setTimeout(function () { showPane(inner, cat.getAttribute('data-pane')); }, 90);
		});
		cat.addEventListener('mouseleave', function () { clearTimeout(timer); });
		cat.addEventListener('focus', function () { if (!mobile.matches) showPane(inner, cat.getAttribute('data-pane')); });
	});
	// Panelet starter altid på første kategori, når det åbnes igen
	document.querySelectorAll('.mm__item[data-mm]').forEach(function (item) {
		item.addEventListener('mouseleave', function () {
			if (mobile.matches) return;
			var inner = item.querySelector('.mm__inner');
			if (inner) showPane(inner, '0');
		});
	});

	// Indholdsfortegnelse: markér afsnittet, man læser, og fyld stregen ned til det
	var toc = document.querySelector('[data-toc]');
	if (toc && 'IntersectionObserver' in window) {
		var links = Array.prototype.slice.call(toc.querySelectorAll('a[href^="#"]'));
		var heads = links.map(function (a) { return document.getElementById(decodeURIComponent(a.getAttribute('href').slice(1))); });
		var list = toc.querySelector('ol');
		var setActive = function (i) {
			links.forEach(function (a, j) { a.classList.toggle('is-active', j === i); });
			if (i < 0) { list.style.setProperty('--toc-fill', '0px'); return; }
			var a = links[i];
			list.style.setProperty('--toc-fill', (a.offsetTop + a.offsetHeight) + 'px');
		};
		var pick = function () {
			var cur = -1;
			heads.forEach(function (h, i) { if (h && h.getBoundingClientRect().top < window.innerHeight * 0.3) cur = i; });
			setActive(cur);
		};
		var io = new IntersectionObserver(pick, { rootMargin: '0px 0px -70% 0px' });
		heads.forEach(function (h) { if (h) io.observe(h); });
		window.addEventListener('scroll', function () { requestAnimationFrame(pick); }, { passive: true });
		pick();
	}

	// Topbar på mobil: fordelene skifter roligt hvert 4. sekund
	var msgs = document.querySelectorAll('.topbar__msg');
	if (msgs.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
		var cur = 0;
		setInterval(function () {
			if (document.hidden) return;
			msgs[cur].classList.remove('is-on');
			cur = (cur + 1) % msgs.length;
			msgs[cur].classList.add('is-on');
		}, 4000);
	}

	// Alle menupaneler får samme højde som det højeste, så panelet ikke hopper, når man skifter punkt.
	// Måles ved første hover (skjult), og igen hvis vinduet ændrer størrelse.
	if (menu) {
		var measured = false;
		var measurePanels = function () {
			if (mobile.matches) return;
			var max = 0;
			menu.querySelectorAll('.mm__item[data-mm]').forEach(function (item) {
				var panel = item.querySelector('.mm__panel');
				var inner = item.querySelector('.mm__inner');
				if (!panel || !inner) return;
				var was = panel.getAttribute('style') || '';
				panel.style.cssText = 'display:block;visibility:hidden;pointer-events:none';
				inner.style.minHeight = '0';
				var active = inner.querySelector('.mm__pane.is-active');
				inner.querySelectorAll('.mm__pane').forEach(function (pane) {
					inner.querySelectorAll('.mm__pane').forEach(function (x) { x.classList.toggle('is-active', x === pane); });
					max = Math.max(max, inner.offsetHeight);
				});
				inner.querySelectorAll('.mm__pane').forEach(function (x) { x.classList.toggle('is-active', x === active); });
				inner.style.minHeight = '';
				panel.setAttribute('style', was);
				if (!was) panel.removeAttribute('style');
			});
			if (max) menu.style.setProperty('--mm-h', max + 'px');
			measured = true;
		};
		menu.addEventListener('mouseenter', function () { if (!measured) measurePanels(); });
		window.addEventListener('resize', function () { measured = false; });
	}

	// Menuens faktiske bredde, så den folder sig jævnt sammen, når søgefeltet vokser
	if (menu && header) {
		menu.querySelectorAll(':scope > .mm__item').forEach(function (it, i) { it.style.setProperty('--i', i); });
		var measure = function () {
			if (header.classList.contains('is-searching')) return;
			header.style.setProperty('--mm-w', menu.scrollWidth + 'px');
		};
		measure();
		window.addEventListener('resize', measure);
		if (document.fonts && document.fonts.ready) document.fonts.ready.then(measure);
	}

	// Smal skærm: luppen folder søgefeltet ud under logoet
	var searchToggle = document.querySelector('.search-toggle');
	var closeSearch = function () {
		if (!header || !header.classList.contains('is-search-open')) return;
		header.classList.remove('is-search-open');
		if (searchToggle) searchToggle.setAttribute('aria-expanded', 'false');
	};
	if (searchToggle && header) {
		searchToggle.addEventListener('click', function () {
			if (header.classList.contains('is-search-open')) { closeSearch(); return; }
			if (drawer && drawer.classList.contains('is-open')) toggle.click();
			header.classList.add('is-search-open');
			searchToggle.setAttribute('aria-expanded', 'true');
			var field = document.getElementById('site-search');
			if (field) field.focus();
		});
	}

	// Mobilmenu: skuffe med lag (menupunkt → kategori). Links med data-to åbner næste lag i stedet for siden.
	var toggle = document.querySelector('.menu-toggle');
	var drawer = document.getElementById('mobilmenu');
	var drawerBg = document.querySelector('[data-close-bg]');
	if (toggle && drawer) {
		var stack = ['root'];
		var view = function (id) { return drawer.querySelector('[data-view="' + id + '"]'); };
		var paint = function () {
			drawer.querySelectorAll('.dr__view').forEach(function (v) {
				var i = stack.indexOf(v.getAttribute('data-view'));
				v.classList.toggle('is-active', i === stack.length - 1);
				v.classList.toggle('is-behind', i > -1 && i < stack.length - 1);
			});
		};
		// Headerens plads, mens skuffen er åben: lige under topbaren, når den kan ses, ellers helt foroven.
		// Regnes ud fra topbaren (ikke headerens aktuelle position), så et åbent tastatur på iPhone ikke flytter den.
		var topbar = document.querySelector('.topbar');
		var placeHeader = function () {
			if (!header || !drawer.classList.contains('is-open')) return;
			var below = topbar ? topbar.offsetTop + topbar.offsetHeight : 0;
			var ht = Math.max(0, below - window.scrollY);
			header.style.position = 'fixed';
			header.style.top = ht + 'px';
			header.style.left = '0';
			header.style.right = '0';
			document.documentElement.style.setProperty('--dr-top', (ht + header.offsetHeight) + 'px');
		};
		var openDrawer = function () {
			if (header) {
				header.classList.remove('is-tucked');
				// Luk søgningen helt (og tastaturet), før menuen åbner
				var act = document.activeElement;
				if (act && header.contains(act) && act.blur) act.blur();
				header.classList.remove('is-searching');
				closeSearch();
			}
			drawer.removeAttribute('inert');
			drawer.setAttribute('aria-hidden', 'false');
			drawer.classList.add('is-open');
			if (drawerBg) { drawerBg.hidden = false; requestAnimationFrame(function () { drawerBg.classList.add('is-open'); }); }
			// Når siden er låst (overflow: hidden), holder "sticky" ikke længere – særligt i Safari på iPhone. Så sidder
			// headeren (med logoet) fast foroven, mens menuen er åben, også når man har scrollet ned.
			placeHeader();
			setTimeout(placeHeader, 350); // når tastaturet er gledet væk
			document.documentElement.classList.add('dr-lock');
			toggle.setAttribute('aria-expanded', 'true');
			toggle.setAttribute('aria-label', 'Luk menu');
		};
		var closeDrawer = function () {
			drawer.classList.remove('is-open');
			if (header) { header.style.position = ''; header.style.top = ''; header.style.left = ''; header.style.right = ''; }
			if (drawerBg) { drawerBg.classList.remove('is-open'); setTimeout(function () { if (!drawer.classList.contains('is-open')) drawerBg.hidden = true; }, 300); }
			drawer.setAttribute('aria-hidden', 'true');
			drawer.setAttribute('inert', '');
			document.documentElement.classList.remove('dr-lock');
			toggle.setAttribute('aria-expanded', 'false');
			toggle.setAttribute('aria-label', 'Åbn menu');
			// Start forfra næste gang, når skuffen er gledet ud
			setTimeout(function () { if (!drawer.classList.contains('is-open')) { stack = ['root']; paint(); drawer.querySelectorAll('.dr__view').forEach(function (v) { v.scrollTop = 0; }); } }, 350);
		};
		toggle.addEventListener('click', function () { if (drawer.classList.contains('is-open')) closeDrawer(); else openDrawer(); });
		window.addEventListener('scroll', placeHeader, { passive: true });
		if (window.visualViewport) window.visualViewport.addEventListener('resize', placeHeader);
		if (drawerBg) drawerBg.addEventListener('click', closeDrawer);
		drawer.addEventListener('click', function (e) {
			var to = e.target.closest('[data-to]');
			if (to && view(to.getAttribute('data-to'))) {
				e.preventDefault();
				var next = view(to.getAttribute('data-to'));
				next.scrollTop = 0;
				stack.push(to.getAttribute('data-to'));
				paint();
				var b = next.querySelector('[data-back]');
				if (b) setTimeout(function () { b.focus({ preventScroll: true }); }, 50);
				return;
			}
			if (e.target.closest('[data-back]') && stack.length > 1) { stack.pop(); paint(); }
		});
		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape' || !drawer.classList.contains('is-open')) return;
			if (stack.length > 1) { stack.pop(); paint(); } else closeDrawer();
		});
		mobile.addEventListener('change', function () { if (!mobile.matches) closeDrawer(); });
		// Kommer man tilbage til siden med browserens tilbage-knap, er skuffen lukket
		window.addEventListener('pageshow', function () { if (drawer.classList.contains('is-open')) closeDrawer(); });
	}

	// Smal skærm: headeren gemmer sig, når man scroller ned, og kommer frem igen, når man scroller op
	if (header) {
		var lastY = window.scrollY, ticking = false;
		window.addEventListener('scroll', function () {
			if (ticking) return;
			ticking = true;
			requestAnimationFrame(function () {
				var y = window.scrollY;
				var tuck = header.classList.contains('is-tucked');
				if (document.documentElement.classList.contains('dr-lock')) { lastY = y; ticking = false; return; }
				if (!mobile.matches || y < 160 || header.classList.contains('is-searching')) tuck = false;
				else if (y > lastY + 4) tuck = true;
				else if (y < lastY - 4) tuck = false;
				header.classList.toggle('is-tucked', tuck);
				lastY = y;
				ticking = false;
			});
		}, { passive: true });
	}

	// Søgning: feltet vokser, menuen toner ud, forslag mens man skriver
	var form = document.querySelector('[data-search]');
	if (form && header) {
		var input = form.querySelector('input[name="s"]');
		var drop = form.querySelector('.search__drop');
		var popular = form.querySelector('.search__popular');
		var results = form.querySelector('.search__results');
		var endpoint = form.getAttribute('data-endpoint');
		var debounce, latest = 0;

		function el(tag, cls, text) {
			var n = document.createElement(tag);
			if (cls) n.className = cls;
			if (text) n.textContent = text;
			return n;
		}
		var placeholder = input.placeholder;
		function open() {
			header.classList.add('is-searching');
			input.placeholder = 'Hvad leder du efter?';
			drop.hidden = false;
		}
		function close() {
			header.classList.remove('is-searching');
			input.placeholder = placeholder;
			drop.hidden = true;
		}
		function render(d, q) {
			results.textContent = '';
			if (d.cats && d.cats.length) {
				var g = el('div', 'search__group');
				g.appendChild(el('span', 'mm__title', 'Kategorier'));
				var wrap = el('div', 'search__cats');
				d.cats.forEach(function (c) { var a = el('a', '', c.name); a.href = c.url; wrap.appendChild(a); });
				g.appendChild(wrap);
				results.appendChild(g);
			}
			if (d.products && d.products.length) {
				var gp = el('div', 'search__group');
				gp.appendChild(el('span', 'mm__title', 'Produkter'));
				var list = el('div', 'search__prods');
				d.products.forEach(function (p) {
					var a = el('a', 'search__prod');
					a.href = p.url;
					if (p.image) { var img = el('img'); img.src = p.image; img.alt = ''; img.loading = 'lazy'; a.appendChild(img); }
					else a.appendChild(el('span', 'search__ph'));
					var name = el('span', 'search__name');
					name.appendChild(el('b', '', p.name));
					if (p.brand) name.appendChild(el('span', '', p.brand));
					a.appendChild(name);
					a.appendChild(el('span', 'search__price', p.price));
					list.appendChild(a);
				});
				gp.appendChild(list);
				results.appendChild(gp);
				var all = el('a', 'search__all', 'Se alle resultater for "' + q + '" →');
				all.href = d.all;
				results.appendChild(all);
			}
			if (!results.childNodes.length) results.appendChild(el('p', 'search__empty', 'Ingen produkter matcher "' + q + '". Prøv et andet ord.'));
			popular.hidden = true;
			results.hidden = false;
		}
		function suggest() {
			var q = input.value.trim();
			if (q.length < 2) { popular.hidden = false; results.hidden = true; return; }
			var id = ++latest;
			fetch(endpoint + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
				.then(function (r) { return r.json(); })
				.then(function (d) { if (id === latest) render(d, q); })
				.catch(function () {});
		}
		input.addEventListener('focus', open);
		form.addEventListener('click', function () { if (!header.classList.contains('is-searching')) input.focus(); });
		input.addEventListener('input', function () { clearTimeout(debounce); debounce = setTimeout(suggest, 180); });
		document.addEventListener('click', function (e) { if (!form.contains(e.target)) close(); });
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && header.classList.contains('is-searching')) { close(); input.blur(); }
		});
		form.addEventListener('submit', function (e) { if (!input.value.trim()) e.preventDefault(); });
		if (input.value.trim()) suggest();
	}

	// Sortering skifter med det samme
	document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
		el.addEventListener('change', function () { el.form.submit(); });
	});

	// Foldbar SEO-tekst
	document.querySelectorAll('[data-seo-toggle]').forEach(function (btn) {
		var text = btn.parentNode.querySelector('[data-seo]');
		if (!text) return;
		if (text.scrollHeight <= 560) { text.classList.remove('is-collapsed'); btn.hidden = true; return; }
		btn.addEventListener('click', function () {
			var collapsed = text.classList.toggle('is-collapsed');
			btn.textContent = collapsed ? 'Læs mere' : 'Vis mindre';
			btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
		});
	});

	// Filtre er foldet sammen på mobil. Kommer man fra en prisknap (#produkter), forbliver panelet lukket.
	if (window.matchMedia('(max-width: 900px)').matches) {
		var f = document.querySelector('.filters__mobile');
		if (f && (!document.querySelector('.tags') || location.hash === '#produkter')) f.removeAttribute('open');
	}
	// Prisknapperne: rul rækken, så den valgte knap står synligt (i midten) i stedet for at starte forfra
	var pp = document.querySelector('.pricepills');
	var ppOn = pp && pp.querySelector('.is-active');
	if (ppOn && pp.scrollWidth > pp.clientWidth) {
		pp.scrollLeft = Math.max(0, ppOn.offsetLeft - (pp.clientWidth - ppOn.offsetWidth) / 2);
	}
	if (pp && location.hash === '#produkter') {
		// Hop ned til knapperne og produkterne, når panelet er lukket (browserens eget hop sker før JS lukker det)
		requestAnimationFrame(function () { pp.scrollIntoView({ block: 'start' }); });
	}
	// Knappen "Filtrer" ved siden af sorteringen åbner/lukker filterlisten på mobil
	var fbtn = document.querySelector('[data-filter-btn]');
	var fbox = document.querySelector('.filters__mobile');
	if (fbtn && fbox) {
		var sync = function () { fbtn.setAttribute('aria-expanded', fbox.open ? 'true' : 'false'); };
		fbtn.addEventListener('click', function () { fbox.open = !fbox.open; sync(); });
		fbox.addEventListener('toggle', sync);
		sync();
	}

	// Nyhedsbrev: sendes til vores endpoint, som videresender til Brevo/MailerLite (double opt-in)
	document.querySelectorAll('[data-newsletter]').forEach(function (form) {
		var note = form.querySelector('.news__note');
		var btn = form.querySelector('button');
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			btn.disabled = true;
			fetch(form.getAttribute('data-endpoint'), {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ email: form.email.value, website: form.website ? form.website.value : '' })
			}).then(function (r) { return r.json(); }).then(function (d) {
				note.textContent = d.message || 'Tak!';
				if (d.ok) form.email.value = '';
			}).catch(function () {
				note.textContent = 'Noget gik galt. Prøv igen senere.';
			}).finally(function () { btn.disabled = false; });
		});
	});
})();

// Underkategorier på kategorikort: tone ud i kanten, så længe der er mere at swipe til
(function () {
	var navs = document.querySelectorAll('.cat__subs, .guide__chips, .pricebox__chips');
	if (!navs.length) return;
	var update = function (n) { n.classList.toggle('has-more', n.scrollWidth > n.clientWidth + 2 && n.scrollLeft + n.clientWidth < n.scrollWidth - 2); };
	navs.forEach(function (n) {
		update(n);
		n.addEventListener('scroll', function () { update(n); }, { passive: true });
	});
	window.addEventListener('resize', function () { navs.forEach(update); });
	window.addEventListener('load', function () { navs.forEach(update); });
})();

// Cookie-samtykke: valget gemmes i den nødvendige førstepartscookie pf_consent (12 måneder, med tidspunkt og version).
(function () {
	var box = document.querySelector('[data-cc]');
	if (!box) return;
	var VERSION = parseInt(box.getAttribute('data-version'), 10) || 1;
	var NAME = 'pf_consent';
	var read = function () {
		var m = document.cookie.match(new RegExp('(?:^|; )' + NAME + '=([^;]*)'));
		if (!m) return null;
		try { var c = JSON.parse(decodeURIComponent(m[1])); return c && c.v === VERSION ? c : null; } catch (e) { return null; }
	};
	var write = function (c) {
		var secure = location.protocol === 'https:' ? '; Secure' : '';
		document.cookie = NAME + '=' + encodeURIComponent(JSON.stringify(c)) + '; Max-Age=31536000; Path=/; SameSite=Lax' + secure;
	};
	var activate = function (c) {
		document.querySelectorAll('script[type="text/plain"][data-consent]').forEach(function (old) {
			var cat = old.getAttribute('data-consent');
			if (!c[cat] || old.getAttribute('data-done')) return;
			old.setAttribute('data-done', '1');
			var s = document.createElement('script');
			Array.prototype.forEach.call(old.attributes, function (a) {
				if (['type', 'data-consent', 'data-src', 'data-done'].indexOf(a.name) === -1) s.setAttribute(a.name, a.value);
			});
			if (old.getAttribute('data-src')) s.src = old.getAttribute('data-src'); else s.text = old.text;
			old.parentNode.insertBefore(s, old.nextSibling);
		});
	};
	var wipe = function () { // tilbagetrukket samtykke: fjern de cookies, siden selv kan nå
		document.cookie.split('; ').forEach(function (kv) {
			var n = kv.split('=')[0];
			if (!n || n === NAME) return;
			['', '; Domain=' + location.hostname, '; Domain=.' + location.hostname.replace(/^www\./, '')].forEach(function (d) {
				document.cookie = n + '=; Max-Age=0; Path=/' + d;
			});
		});
	};
	var views = function (name) {
		box.querySelectorAll('[data-cc-view]').forEach(function (v) { v.hidden = v.getAttribute('data-cc-view') !== name; });
	};
	var show = function (name) {
		var c = read() || {};
		box.querySelectorAll('[data-cc-cat]').forEach(function (i) { i.checked = !!c[i.getAttribute('data-cc-cat')]; });
		views(name || 'main');
		box.hidden = false;
		box.focus({ preventScroll: true });
	};
	var save = function (stat, mark) {
		var before = read();
		var withdrew = before && ((before.statistik && !stat) || (before.marketing && !mark));
		write({ v: VERSION, t: new Date().toISOString(), statistik: !!stat, marketing: !!mark });
		box.hidden = true;
		if (withdrew) { wipe(); location.reload(); return; }
		activate({ statistik: !!stat, marketing: !!mark });
		document.dispatchEvent(new CustomEvent('pf:consent', { detail: { statistik: !!stat, marketing: !!mark } }));
	};
	box.addEventListener('click', function (e) {
		var b = e.target.closest('[data-cc-act]');
		if (!b) return;
		var a = b.getAttribute('data-cc-act');
		if (a === 'accept') save(true, true);
		else if (a === 'reject') save(false, false);
		else if (a === 'settings') show('settings');
		else if (a === 'save') {
			var st = box.querySelector('[data-cc-cat="statistik"]').checked, mk = box.querySelector('[data-cc-cat="marketing"]').checked;
			save(st, mk);
		}
	});
	document.querySelectorAll('[data-cc-open]').forEach(function (b) { b.addEventListener('click', function () { show('settings'); }); });
	var c = read();
	if (c) activate(c); else show('main');
})();

// Kategori-faner: slider med fade, træk med musen og pile på desktop
(function () {
	document.querySelectorAll('nav.tabs').forEach(function (tabs) {
		var wrap = document.createElement('div');
		wrap.className = 'tabs-wrap';
		tabs.parentNode.insertBefore(wrap, tabs);
		wrap.appendChild(tabs);
		var mk = function (dir, label) {
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'tabs__arrow tabs__arrow--' + dir;
			b.setAttribute('aria-label', label);
			b.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>';
			wrap.appendChild(b);
			return b;
		};
		var prev = mk('prev', 'Vis tidligere kategorier');
		var next = mk('next', 'Vis flere kategorier');
		var update = function () {
			var more = tabs.scrollLeft + tabs.clientWidth < tabs.scrollWidth - 2;
			var back = tabs.scrollLeft > 2;
			tabs.classList.toggle('has-more', more);
			tabs.classList.toggle('has-prev', back);
			wrap.classList.toggle('has-more', more);
			wrap.classList.toggle('has-prev', back);
		};
		var step = function (d) { tabs.scrollBy({ left: d * Math.max(200, tabs.clientWidth * 0.7), behavior: 'smooth' }); };
		prev.addEventListener('click', function () { step(-1); });
		next.addEventListener('click', function () { step(1); });
		tabs.addEventListener('scroll', update, { passive: true });
		window.addEventListener('resize', update);
		// Aktiv fane skal være synlig
		var act = tabs.querySelector('.is-active, [aria-current]');
		if (act) { tabs.scrollLeft = Math.max(0, act.offsetLeft - tabs.clientWidth / 2 + act.offsetWidth / 2); }
		update();
		window.addEventListener('load', function () { if (act) { tabs.scrollLeft = Math.max(0, act.offsetLeft - tabs.clientWidth / 2 + act.offsetWidth / 2); } update(); });
		// Træk med musen
		var down = false, moved = false, sx = 0, sl = 0;
		tabs.addEventListener('pointerdown', function (e) { if (e.pointerType !== 'mouse') return; down = true; moved = false; sx = e.clientX; sl = tabs.scrollLeft; });
		window.addEventListener('pointermove', function (e) {
			if (!down) return;
			var dx = e.clientX - sx;
			if (Math.abs(dx) > 5) { moved = true; tabs.classList.add('is-dragging'); }
			if (moved) tabs.scrollLeft = sl - dx;
		});
		window.addEventListener('pointerup', function () { down = false; tabs.classList.remove('is-dragging'); });
		tabs.addEventListener('click', function (e) { if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; } }, true);
	});
})();

/* Kampagnebaren: teksten sænker farten blødt, når musen er over den (Web Animations API – ingen hop). */
(function () {
	var track = document.querySelector('.campaign-bar__track');
	var run = track && track.querySelector('.campaign-bar__run');
	if (!run || !run.getAnimations) return;
	var target = 1, rate = 1, raf = 0;
	function step() {
		rate += (target - rate) * 0.08;
		if (Math.abs(target - rate) < 0.005) rate = target;
		run.getAnimations().forEach(function (a) { a.playbackRate = rate; });
		raf = rate === target ? 0 : requestAnimationFrame(step);
	}
	function to(r) { target = r; if (!raf) raf = requestAnimationFrame(step); }
	track.addEventListener('mouseenter', function () { to(0.3); });
	track.addEventListener('mouseleave', function () { to(1); });
})();


// Kampagneheaderens knap: glid blødt ned til produkterne i stedet for at hoppe (også i browsere uden CSS scroll-behavior).
document.addEventListener('click', function (e) {
	var a = e.target.closest && e.target.closest('.camp-hero__btn[href^="#"]');
	if (!a) return;
	var t = document.querySelector(a.getAttribute('href'));
	if (!t) return;
	e.preventDefault();
	var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	t.scrollIntoView({ behavior: calm ? 'auto' : 'smooth', block: 'start' });
	if (history.replaceState) history.replaceState(null, '', a.getAttribute('href'));
});
