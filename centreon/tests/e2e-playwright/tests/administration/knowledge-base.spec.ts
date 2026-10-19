import { randomUUID } from 'node:crypto';

import type { Browser } from '@playwright/test';

import { adminUser } from '../../fixtures/credentials';
import { expect, test } from '../../fixtures/test';
import {
  ensureStack,
  execInWebContainer,
  publishedPort,
  queryCentreonDb
} from '../../helpers/docker';
import { signIn } from '../../helpers/session';
import {
  KnowledgeBaseParametersPage,
  KnowledgeListingPage
} from '../../pages/KnowledgeBasePages';

/**
 * Replaces Knowledges/01: a procedure written in the wiki gives its host or
 * service a note URL once synchronized, and deleting it from Centreon
 * removes it. Needs the docker compose `mediawiki` profile.
 */
const wiki = {
  account: 'admin',
  password: 'Centreon!2021',
  url: 'http://mediawiki'
};
const proxy = './include/configuration/configKnowledge/proxy/proxy.php';

const synchronize = (): void => {
  execInWebContainer(
    'php /usr/share/centreon/cron/centKnowledgeSynchronizer.php'
  );
};

/** Write a wiki page, as the wiki users would, in a browser of its own. */
const writeProcedure = async (
  browser: Browser,
  title: string
): Promise<void> => {
  const context = await browser.newContext({
    storageState: { cookies: [], origins: [] }
  });
  try {
    const page = await context.newPage();
    await page.goto(
      `http://localhost:${publishedPort('mediawiki', 80)}/index.php?title=${title}&action=edit`
    );
    await page.locator('#wpTextbox1').fill(`Procedure of ${title}`);
    await Promise.all([
      page.waitForEvent('load'),
      page.locator('#wpSave').click()
    ]);
  } finally {
    await context.close();
  }
};

test.beforeAll(async () => {
  test.setTimeout(300_000);
  await ensureStack({ profiles: ['mediawiki'], services: ['mediawiki'] });
});

test('links the wiki procedures to their host and service, until deleted', async ({
  adminApi,
  browser,
  page
}) => {
  test.setTimeout(180_000);
  const host = `pw-kb-${randomUUID().slice(0, 6)}`;
  const service = `${host}-svc`;
  await adminApi.provision([
    {
      action: 'ADD',
      object: 'HOST',
      values: `${host};${host};192.0.2.1;generic-host;Central;`
    },
    {
      action: 'ADD',
      object: 'SERVICE',
      values: `${host};${service};generic-service`
    }
  ]);
  const notesUrl = () =>
    queryCentreonDb(
      `SELECT IFNULL(e.ehi_notes_url, 'NULL'), IFNULL(s.esi_notes_url, 'NULL') FROM host h JOIN extended_host_information e ON e.host_host_id = h.host_id JOIN host_service_relation r ON r.host_host_id = h.host_id JOIN extended_service_information s ON s.service_service_id = r.service_service_id WHERE h.host_name = '${host}'`
    )
      .trim()
      .split('\t');

  try {
    await signIn(page, adminUser);
    await new KnowledgeBaseParametersPage(page).configure(wiki);

    await test.step('The procedures written in the wiki become note URLs', async () => {
      await writeProcedure(browser, `Host_:_${host}`);
      await writeProcedure(browser, `Service_:_${host}_/_${service}`);
      synchronize();
      expect(notesUrl()).toEqual([
        `${proxy}?host_name=$HOSTNAME$`,
        `${proxy}?host_name=$HOSTNAME$&service_description=$SERVICEDESC$`
      ]);
    });

    await test.step('A procedure deleted from Centreon goes away', async () => {
      const procedures = new KnowledgeListingPage(page, 'host');
      await procedures.open(host);
      await procedures.deletePage(host);
      await procedures.open(host);
      await expect(
        procedures.row(host).getByText('Delete wiki page')
      ).toHaveCount(0);
    });
  } finally {
    await adminApi.cleanUp([{ action: 'DEL', object: 'HOST', values: host }]);
  }
});
