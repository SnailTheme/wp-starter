/**
 * Real-browser reveal regression matrix against built theme assets.
 * Install Playwright separately, then set ST_PLAYWRIGHT_MODULE to its index.mjs
 * if it is not locally resolvable. ST_BROWSER_EXECUTABLE selects Brave or another
 * Chromium executable; otherwise Playwright uses its installed Chromium.
 * Usage: node .github/tests/animation-reveal-browser.mjs THEME [BLUEPRINT TAILWIND]
 * No network, WordPress, database, or project-content writes are needed.
 */
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
const {chromium} = await import(process.env.ST_PLAYWRIGHT_MODULE || 'playwright');
const roots = process.argv.slice(2);
if (!roots.length) throw new Error('Pass at least one built theme directory.');
const browser = await chromium.launch({
    headless: true,
    ...(process.env.ST_BROWSER_EXECUTABLE ? {executablePath: process.env.ST_BROWSER_EXECUTABLE} : {})
});
let assertions = 0;
let cases = 0;
const results = [];
const manualObserver = `
window.__observers=[];
window.IntersectionObserver=class {
  constructor(callback, options){this.callback=callback;this.options=options;this.targets=new Set();window.__observers.push(this);}
  observe(target){this.targets.add(target);}
  unobserve(target){this.targets.delete(target);}
  disconnect(){this.targets.clear();}
};
window.__intersect=()=>{for(const observer of window.__observers){observer.callback([...observer.targets].map(target=>({target,isIntersecting:true})));}};
`;
const markup = (id = 'item', token = 'animate__fadeIn') =>
    `<section class="enter-view" id="wrap-${id}"><div id="${id}" class="animate__animated opacity-0" data-animate-class="${token}">Reading content <button id="focus-${id}">Focus control</button></div></section>`;
function check(value, message) { assert.ok(value, message); assertions++; }
async function visible(page, id = 'item', timeout = 3000) {
    await page.waitForFunction(id => {
        const el = document.getElementById(id), style = getComputedStyle(el);
        return style.opacity === '1' && style.visibility === 'visible'
            && !el.classList.contains('st-wp-reveal--pending')
            && !el.hasAttribute('data-st-wp-reveal');
    }, id, {timeout});
    check(true, id + ' is visibly settled');
}
async function pending(page, id = 'item') {
    const state = await page.$eval('#' + id, el => ({opacity:getComputedStyle(el).opacity, state:el.dataset.stWpReveal}));
    check(state.opacity === '0' && state.state === 'pending', id + ' was not hidden only after arming: ' + JSON.stringify(state));
}

try {
    for (const root of roots) {
        const state = JSON.parse(await fs.readFile(path.join(root, 'st-toolkit.json'), 'utf8'));
        const profile = state.ui_profile;
        const read = relative => fs.readFile(path.join(root, relative), 'utf8');
        const css = await read('assets/css/main.min.css');
        const editor = await read('assets/css/editor.min.css');
        const guard = await read('assets/css/styles-enqueue/animation-reveal.min.css');
        const optional = await read('assets/css/styles-register/plugins/animatecss/animate.min.css');
        const source = await read('assets/scripts/scripts-register/plugins/animatecss/animate.js');
        const built = await read('assets/js/scripts-register/plugins/animatecss/animate.min.js');

        async function run(name, callback, options = {}) {
            const context = await browser.newContext({viewport:{width:1024,height:600}, ...options});
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            async function mount(config = {}) {
                const html = `<!doctype html><html><head><style>${css}</style><style>${guard}</style>`
                    + (config.css === false ? '' : `<style id="optional">${config.animationCSS ?? optional}</style>`)
                    + `<style>body{margin:0}.enter-view{min-height:80px}.spacer{height:1400px}.animate__animated{--animate-duration:.08s}.outside.opacity-0{opacity:0!important}${config.extraCSS || ''}</style>`
                    + `</head><body>${config.offscreen ? '<div class="spacer"></div>' : ''}${config.markup || markup()}`
                    + `<p id="outside" class="outside opacity-0">Unrelated hidden utility</p>`
                    + `<script>${config.before || ''}</script>`
                    + (config.early && config.js !== false ? `<script>${config.source ? source : built}</script>` : '')
                    + '</body></html>';
                await page.setContent(html);
                if (!config.early && config.js !== false) await page.addScriptTag({content:config.source ? source : built});
            }
            try {
                await callback({page, mount, context});
                check(errors.length === 0, 'Uncaught browser errors: ' + errors.join('; '));
                cases++;
                results.push({profile, test:name, status:'passed'});
                console.log(profile + ': ' + name);
            } catch (error) {
                throw new Error(profile + ': ' + name + ': ' + error.message, {cause:error});
            } finally {
                await context.close();
            }
        }

        for (const config of [{css:false,js:false},{css:true,js:false},{css:false,js:true},{css:true,js:true}]) {
            await run('asset combination ' + JSON.stringify(config), async ({page,mount}) => {
                await mount({...config, offscreen:true});
                if (config.css && config.js) {
                    await pending(page);
                    await page.locator('#item').scrollIntoViewIfNeeded();
                }
                await visible(page);
                check(await page.$eval('#outside', el => getComputedStyle(el).opacity) === '0', 'Unrelated opacity utility changed');
            });
        }
        for (const optionalCSS of [false, true]) {
            for (const offscreen of [false, true]) {
                await run('JavaScript disabled, content stays visible '+JSON.stringify({optionalCSS,offscreen}), async ({page,mount}) => {
                    const nodes = markup('legacy')
                        + markup('modern').replace('animate__animated opacity-0', 'animate__animated')
                        + markup('self-legacy').replace('class="animate__animated opacity-0"', 'class="enter-view animate__animated opacity-0"')
                        + markup('self-modern').replace('class="animate__animated opacity-0"', 'class="enter-view animate__animated"');
                    await mount({early:true,css:optionalCSS,offscreen,markup:nodes,before:'window.__scriptExecuted=true;'});
                    check(await page.evaluate(() => typeof window.__scriptExecuted === 'undefined' && typeof window.stWpReveal === 'undefined'), 'Browser executed scripts despite JavaScript being disabled');
                    for (const id of ['legacy','modern','self-legacy','self-modern']) {
                        const readable = () => page.$eval('#'+id, el => {
                            const style=getComputedStyle(el), rect=el.getBoundingClientRect();
                            return style.opacity === '1' && style.visibility === 'visible' && style.display !== 'none'
                                && style.animationName === 'none' && rect.width > 0 && rect.height > 0
                                && !el.hasAttribute('data-st-wp-reveal') && !el.classList.contains('st-wp-reveal--pending');
                        });
                        check(await readable(), id+' was hidden before scrolling with JavaScript disabled');
                        await page.locator('#'+id).scrollIntoViewIfNeeded();
                        check(await readable(), id+' did not remain readable after scrolling with JavaScript disabled');
                    }
                    check(await page.$eval('#outside', el => getComputedStyle(el).opacity) === '0', 'No-JS safeguard changed an unrelated hidden utility');
                }, {javaScriptEnabled:false});
            }
        }
        await run('important layered utilities remain scoped', async ({page,mount}) => {
            await mount({js:false,extraCSS:'@layer utilities{.opacity-0{opacity:0!important}}'});
            check(await page.$eval('#item',el=>getComputedStyle(el).opacity)==='1','Layered important utility defeated the reveal safeguard');
            check(await page.$eval('#outside',el=>getComputedStyle(el).opacity)==='0','Safeguard overrode an unrelated utility');
        });
        await run('early startup arms before revealing', async ({page,mount}) => {
            await mount({early:true, before:manualObserver});
            await pending(page);
            await page.evaluate(() => window.__intersect());
            await visible(page);
            check(await page.$eval('#wrap-item', el => el.classList.contains('entered')), 'Wrapper compatibility class missing');
        });
        await run('source and generated runtime agree', async ({page,mount}) => {
            await mount({early:true, before:manualObserver, source:true});
            await pending(page);
            await page.evaluate(() => window.__intersect());
            await visible(page);
        });
        await run('fade enters once from hidden without a visible-hide flash', async ({page,mount}) => {
            await mount({early:true,before:manualObserver,extraCSS:'.animate__animated{--animate-duration:.3s}'});
            await pending(page);
            const animationName=await page.evaluate(()=>{
                const el=document.getElementById('item');window.__fadeSamples=[Number(getComputedStyle(el).opacity)];window.__intersect();
                const name=getComputedStyle(el).animationName;
                const sample=()=>{window.__fadeSamples.push(Number(getComputedStyle(el).opacity));if(el.hasAttribute('data-st-wp-reveal')&&window.__fadeSamples.length<120)requestAnimationFrame(sample);};
                requestAnimationFrame(sample);return name;
            });
            check(animationName==='fadeIn','Selected animation never started');
            await visible(page);
            await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(resolve)));
            const samples=await page.evaluate(()=>window.__fadeSamples);
            check(samples[0]===0 && samples.at(-1)===1,'Fade did not travel from hidden to visible');
            check(samples.some(value=>value>0&&value<1),'No real intermediate fade frame was observed');
            check(samples.every((value,index)=>index===0||value>=samples[index-1]-.001),'Fade showed content and then hid it again');
        });
        await run('late on-screen startup never hides/fades painted content', async ({page,mount}) => {
            await mount({js:false});
            await page.evaluate(() => {
                window.__opacities=[];
                window.__frame=()=>{window.__opacities.push(getComputedStyle(document.getElementById('item')).opacity);requestAnimationFrame(window.__frame);};
                requestAnimationFrame(window.__frame);
            });
            await page.addScriptTag({content:built});
            await visible(page);
            await page.waitForTimeout(150);
            check(await page.evaluate(() => window.__opacities.length > 0 && window.__opacities.every(value => value === '1')), 'Late startup caused a visible-hide-fade flash');
        });
        for (const content of [
            '<span style="position:absolute;top:50px">Visible overflowing child</span>',
            'Visible text in a zero-height box',
            '<div style="height:0"><div style="position:absolute;top:50px;width:80px;height:30px;background:red"></div></div>',
            '<div style="height:0"><img alt="Overflowing image" style="position:absolute;top:50px;width:80px;height:30px" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"></div>'
        ]) {
            await run('late startup preserves painted zero-height overflow: '+content.slice(0,20),async({page,mount})=>{
                const html='<section class="enter-view"><div id="item" class="animate__animated opacity-0" data-animate-class="animate__fadeIn" style="position:relative;height:0;line-height:24px">'+content+'</div></section>';
                await mount({js:false,markup:html,before:manualObserver});
                await page.addScriptTag({content:built});
                await visible(page,'item',1000);
                check(await page.evaluate(()=>window.__observers.length===0),'Already-painted overflow was armed');
            });
        }
        for (const style of ['animation-duration:0s','animation-duration:20s','animation-name:none!important','animation-play-state:paused','animation-iteration-count:infinite']) {
            await run('actual element override '+style,async({page,mount})=>{
                await mount({offscreen:true,before:manualObserver,markup:markup().replace('id="item"','id="item" style="'+style+'"')});
                await visible(page,'item',1000);
                check(await page.evaluate(()=>window.__observers.length===0),'Unusable actual-element animation was armed');
            });
        }
        await run('element-specific important name rule is respected',async({page,mount})=>{
            await mount({offscreen:true,before:manualObserver,extraCSS:'div#item[data-animate-class]{animation-name:none!important}'});
            await visible(page,'item',1000);
            check(await page.evaluate(()=>window.__observers.length===0),'Element-specific disabling rule was ignored');
        });
        await run('existing project animation is not mistaken for an unknown reveal',async({page,mount})=>{
            await mount({offscreen:true,before:manualObserver,markup:markup('item','animate__missing'),extraCSS:'@keyframes project-motion{from{color:red}to{color:blue}}#item{animation:project-motion 2s}'});
            await visible(page,'item',1000);
            check(await page.$eval('#item',el=>getComputedStyle(el).animationName)==='project-motion','Unrelated project animation was removed');
            check(await page.evaluate(()=>window.__observers.length===0),'Unknown reveal borrowed an unrelated animation');
        });
        await run('preflight produces no extra CSS animation events',async({page,mount})=>{
            const listeners='window.__starts=0;window.__cancels=0;document.getElementById("item").addEventListener("animationstart",()=>window.__starts++);document.getElementById("item").addEventListener("animationcancel",()=>window.__cancels++);';
            await mount({early:true,before:manualObserver+listeners});
            await pending(page);
            await page.waitForTimeout(80);
            check(await page.evaluate(()=>window.__starts===0&&window.__cancels===0),'Preflight emitted extra animation events');
            await page.evaluate(()=>window.__intersect());await visible(page);
            check(await page.evaluate(()=>window.__starts===1&&window.__cancels===0),'Selected reveal did not emit exactly one normal start');
        });
        await run('repeat evaluation and initialization are idempotent', async ({page,mount}) => {
            await mount({early:true, before:manualObserver});
            await pending(page);
            for (let i=0;i<3;i++) await page.addScriptTag({content:built});
            await page.evaluate(() => {for(let i=0;i<20;i++)window.stWpReveal.init(document);});
            check(await page.evaluate(() => window.__observers.length === 1 && window.__observers[0].targets.size === 1), 'Observers multiplied');
            await page.evaluate(() => window.__intersect());
            await visible(page);
            await page.evaluate(() => window.stWpReveal.init(document, {beforePaint:true}));
            check(await page.$eval('#item', el => !el.hasAttribute('data-st-wp-reveal')), 'Completed item replayed');
        });
        await run('invalid and unknown tokens do not interrupt neighbors', async ({page,mount}) => {
            const tokens = ['', 'two tokens', ' animate__fadeIn', 'animate__fadeIn ', 'animate__bad/', 'animate__disabled'];
            await mount({offscreen:true, before:manualObserver, markup:tokens.map((token,index)=>markup('bad'+index,token)).join('') + markup('valid') + '<section class="enter-view"><p id="missing" class="animate__animated opacity-0">Missing token</p></section>'});
            for(let i=0;i<tokens.length;i++) await visible(page,'bad'+i);
            await visible(page,'missing');
            await pending(page,'valid');
            await page.evaluate(() => window.__intersect());
            await visible(page,'valid');
        });
        if(process.env.ST_REVEAL_SUBSET_CSS){
            const subset=await fs.readFile(process.env.ST_REVEAL_SUBSET_CSS,'utf8');
            await run('project-selected Sass subset rejects an omitted animation',async({page,mount})=>{
                await mount({offscreen:true,before:manualObserver,animationCSS:subset,markup:markup('omitted','animate__fadeOut')+markup('selected')});
                await visible(page,'omitted');await pending(page,'selected');
                await page.evaluate(()=>window.__intersect());await visible(page,'selected');
            });
        }
        for (const setup of [
            'window.IntersectionObserver=undefined;',
            'window.IntersectionObserver=class{constructor(){throw Error("constructor");}};',
            'window.IntersectionObserver=class{observe(){throw Error("observe");}unobserve(){}disconnect(){}};',
            'window.matchMedia=undefined;'
        ]) {
            await run('unsupported/failed setup ' + setup.slice(0,45), async ({page,mount}) => {
                await mount({offscreen:true, before:setup});
                await visible(page);
            });
        }
        await run('partial observation failure releases all armed content', async ({page,mount}) => {
            await mount({offscreen:true, markup:markup('first')+markup('second'), before:manualObserver + 'window.IntersectionObserver.prototype.observe=function(target){if(this.targets.size)throw Error("second observe");this.targets.add(target);};'});
            await visible(page,'first'); await visible(page,'second');
            check(await page.evaluate(()=>window.__observers.every(observer=>observer.targets.size===0)), 'Partial setup retained observations');
        });
        await run('one failed item does not disable neighboring animations', async ({page,mount}) => {
            await mount({offscreen:true,markup:markup('bad')+markup('good'),before:manualObserver});
            await pending(page,'bad');await pending(page,'good');
            await page.evaluate(()=>{document.getElementById('bad').getAnimations=()=>{throw Error('failed animation inspection');};window.__intersect();});
            await visible(page,'bad');await visible(page,'good');
        });
        await run('dynamic wrapper, child and root-self initialization', async ({page,mount}) => {
            await mount({offscreen:true, before:manualObserver});
            await page.evaluate(html => {
                document.body.insertAdjacentHTML('beforeend', html);
                window.stWpReveal.init(document.getElementById('wrap-later'));
                const child = document.createElement('p');child.id='child';child.className='animate__animated opacity-0';child.dataset.animateClass='animate__fadeIn';
                document.getElementById('wrap-item').append(child);window.stWpReveal.init(child);
                const self=document.createElement('section');self.id='self';self.className='enter-view animate__animated opacity-0';self.dataset.animateClass='animate__fadeIn';document.body.append(self);window.stWpReveal.init(self);
            }, markup('later'));
            for(const id of ['item','later','child','self'])await pending(page,id);
            await page.evaluate(()=>window.__intersect());
            for(const id of ['item','later','child','self'])await visible(page,id);
        });
        await run('fresh visible insertion opts in only before paint', async ({page,mount}) => {
            await mount({js:false, before:manualObserver});
            await page.addScriptTag({content:built});
            await page.evaluate(html=>{document.body.insertAdjacentHTML('beforeend',html);window.stWpReveal.init(document.getElementById('wrap-fresh'),{beforePaint:true});},markup('fresh'));
            await pending(page,'fresh');
            await page.evaluate(()=>window.__intersect());
            await visible(page,'fresh');
        });
        await run('focused controls reveal pending ancestors', async ({page,mount}) => {
            await mount({offscreen:true, before:manualObserver});
            await pending(page);
            await page.locator('#focus-item').focus();
            await visible(page);
            check(await page.$eval('#focus-item',el=>document.activeElement===el),'Focus was lost');
        });
        await run('initial reduced motion skips hiding', async ({page,mount}) => {
            await mount({offscreen:true});await visible(page);
        }, {reducedMotion:'reduce'});
        await run('changed reduced motion settles pending and running', async ({page,mount}) => {
            await mount({offscreen:true,before:manualObserver});await pending(page);
            await page.emulateMedia({reducedMotion:'reduce'});await visible(page);
            await page.emulateMedia({reducedMotion:'no-preference'});
            await page.evaluate(()=>window.stWpReveal.init(document,{beforePaint:true}));await visible(page);
        });
        await run('print reveals without scrolling', async ({page,mount}) => {
            await mount({offscreen:true,before:manualObserver});await pending(page);
            await page.emulateMedia({media:'print'});await visible(page);
            check(await page.$eval('#item',el=>getComputedStyle(el).transform)==='none','Print retained an animation transform');
        });
        await run('beforeprint blocks insertions until afterprint', async ({page,mount}) => {
            await mount({offscreen:true,before:manualObserver});await pending(page);
            await page.evaluate(html=>{window.dispatchEvent(new Event('beforeprint'));document.body.insertAdjacentHTML('beforeend',html);window.stWpReveal.init(document.getElementById('wrap-printing'),{beforePaint:true});},markup('printing'));
            await visible(page);await visible(page,'printing');
            await page.evaluate(html=>{window.dispatchEvent(new Event('afterprint'));document.body.insertAdjacentHTML('beforeend',html);window.stWpReveal.init(document.getElementById('wrap-afterprint'),{beforePaint:true});},markup('afterprint'));
            await pending(page,'afterprint');await page.evaluate(()=>window.__intersect());await visible(page,'afterprint');
        });
        await run('reduced motion interrupts a running animation', async ({page,mount}) => {
            await mount({early:true,before:manualObserver,extraCSS:'.animate__animated{--animate-duration:2s}'});
            await pending(page);await page.evaluate(()=>window.__intersect());
            await page.emulateMedia({reducedMotion:'reduce'});await visible(page,'item',1000);
        });
        await run('animation cancellation restores content', async ({page,mount}) => {
            await mount({early:true,before:manualObserver});await pending(page);
            await page.evaluate(()=>{window.__intersect();document.getElementById('item').getAnimations().forEach(animation=>animation.cancel());});
            await visible(page,'item',1000);
        });
        await run('canceling CSS load cannot leave pending content hidden', async ({page,mount}) => {
            await mount({offscreen:true,before:manualObserver});await pending(page);
            await page.evaluate(()=>document.getElementById('optional').remove());
            check(await page.$eval('#item',el=>getComputedStyle(el).opacity)==='1','Missing optional CSS still hid the item');
            await page.evaluate(()=>window.__intersect());await visible(page);
        });
        for (const extra of ['animate__infinite','animate__delay-1s','animate__repeat-2']) {
            await run('duration/modifier ' + extra, async ({page,mount}) => {
                await mount({offscreen:true,before:manualObserver,markup:markup().replace('animate__animated opacity-0','animate__animated opacity-0 '+extra)});
                if(extra==='animate__infinite'){await visible(page);}else{await pending(page);await page.evaluate(()=>window.__intersect());await visible(page);}
            });
        }
        await run('scoped destroy releases only the selected subtree', async ({page,mount}) => {
            await mount({offscreen:true,before:manualObserver,markup:markup('first')+markup('second')});
            await pending(page,'first');await pending(page,'second');
            await page.evaluate(()=>window.stWpReveal.destroy(document.getElementById('wrap-first')));
            await visible(page,'first');await pending(page,'second');
            await page.evaluate(()=>window.stWpReveal.destroy(document));await visible(page,'second');
            check(await page.evaluate(()=>window.__observers.every(observer=>observer.targets.size===0)),'destroy retained observers');
        });
        await run('expired observations are bounded', async ({page,mount}) => {
            await mount({offscreen:true,before:manualObserver+'const nativeTimeout=window.setTimeout;window.setTimeout=(fn,ms,...args)=>nativeTimeout(fn,ms===30000?30:ms,...args);'});
            await visible(page);
            check(await page.evaluate(()=>window.__observers.every(observer=>observer.targets.size===0)),'Observation lease leaked');
        });
        await run('watchdog restores an exit animation when completion events are lost', async ({page,mount}) => {
            const failures='const nativeTimeout=window.setTimeout;window.setTimeout=(fn,ms,...args)=>nativeTimeout(fn,ms===10000?150:ms,...args);const add=EventTarget.prototype.addEventListener;EventTarget.prototype.addEventListener=function(type,...args){if(["animationend","animationcancel","finish","cancel"].includes(type))return;return add.call(this,type,...args);};';
            await mount({early:true,before:manualObserver+failures,markup:markup('item','animate__fadeOut')});
            await pending(page);await page.evaluate(()=>window.__intersect());await visible(page,'item',1000);
        });
        await run('iframe editor remains visible and unanimated', async ({page,mount}) => {
            await mount({js:false});
            const frameHTML = '<!doctype html><html class="block-editor-iframe__html"><head><style>'+editor+'</style><style>'+guard+'</style><style>'+optional+'</style></head><body class="editor-styles-wrapper">'+markup()+'<script>'+built+'</script></body></html>';
            await page.evaluate(html=>{const iframe=document.createElement('iframe');iframe.id='editor';iframe.srcdoc=html;document.body.append(iframe);},frameHTML);
            const frame=page.frameLocator('#editor');
            await frame.locator('#item').waitFor();
            check(await frame.locator('#item').evaluate(el=>getComputedStyle(el).opacity)==='1','Iframe content hidden');
            check(await frame.locator('#item').evaluate(el=>!el.hasAttribute('data-st-wp-reveal')),'Editor animation initialized');
        });
        for (const viewport of [{width:390,height:180},{width:568,height:240},{width:1440,height:900}]) {
            await run('bottom-of-page at ' + viewport.width + 'x' + viewport.height, async ({page,mount}) => {
                await mount({offscreen:true});await pending(page);
                await page.evaluate(()=>window.scrollTo(0,document.documentElement.scrollHeight));
                await visible(page);
                if(process.env.ST_REVEAL_ARTIFACTS){await fs.mkdir(process.env.ST_REVEAL_ARTIFACTS,{recursive:true});await page.screenshot({path:path.join(process.env.ST_REVEAL_ARTIFACTS,profile+'-'+viewport.width+'.png')});}
            }, {viewport});
        }
    }
    console.log(JSON.stringify({status:'passed',browser:browser.version(),profiles:roots.length,cases,assertions},null,2));
    if(process.env.ST_REVEAL_ARTIFACTS)await fs.writeFile(path.join(process.env.ST_REVEAL_ARTIFACTS,'results.json'),JSON.stringify(results,null,2));
} finally {
    await browser.close();
}
