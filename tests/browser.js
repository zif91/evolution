// Run with a Playwright Page already logged in to the local CMS manager.
async (page) => {
  const results = [];
  const check = (name, value) => { if (!value) throw new Error(name); results.push({test:name,status:'PASS'}); };
  let response = await page.goto('http://127.0.0.1:8133/');
  check('Public homepage HTTP 200', response.status() === 200);
  check('Legacy snippet', (await page.locator('#legacy-result').innerText()).includes('snippet parameters: passed'));
  check('Legacy plugin OnWebPagePrerender', (await page.locator('#plugin-result').innerText()).includes('OK'));
  const docLister = () => page.locator('section').filter({has:page.getByRole('heading',{name:'DocLister',exact:true})}).getByRole('listitem');
  check('DocLister rendered database resource', (await docLister().allTextContents()).some(text => text.includes('Laravel 13')));
  await page.getByRole('button',{name:'Test form',exact:true}).click();
  await page.getByText('Enter your name',{exact:true}).waitFor();
  check('FormLister rejects invalid POST', await page.getByText('Enter your name',{exact:true}).isVisible());
  await page.locator('input[name=name]').fill('Browser regression test');
  await page.getByRole('button',{name:'Test form',exact:true}).click();
  await page.locator('#form-success').waitFor();
  check('FormLister accepts valid POST (noemail)', (await page.locator('#form-success').innerText()).includes('successfully'));
  response = await page.goto('http://127.0.0.1:8133/manager/?a=27&id=1');
  check('Authenticated manager edit HTTP 200', response.status() === 200 && await page.locator('input[name=pagetitle]').count() === 1);
  const title = 'Evolution CMS on Laravel 13 — browser verified';
  await page.locator('input[name=pagetitle]').fill(title);
  await page.locator('input[name=description]').fill('Saved in the real manager after upgrading Illuminate 8 to 13.');
  await page.getByRole('link',{name:/Save.*Continue editing/}).click();
  await page.waitForURL(/a=27.*id=1.*stay=2/);
  check('Manager saved resource', await page.locator('input[name=pagetitle]').inputValue() === title);
  await page.screenshot({path:'reports/local-manager.png',fullPage:true});
  await page.goto('http://127.0.0.1:8133/');
  check('Saved resource visible on frontend through DocLister', (await docLister().allTextContents()).includes(title));
  await page.screenshot({path:'reports/local-home.png',fullPage:true});
  const isolated = await page.context().browser().newContext();
  try {
    const guest = await isolated.newPage();
    const denied = await guest.goto('http://127.0.0.1:8133/manager/?a=27&id=1');
    check('Guest cannot edit resources', await guest.locator('input[name=password]').count() === 1 && await guest.locator('input[name=pagetitle]').count() === 0);
    await guest.close();
  } finally { await isolated.close(); }
  return results;
}
