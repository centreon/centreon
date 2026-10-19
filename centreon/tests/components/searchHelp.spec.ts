import {
  cloudDocsURL,
  getOnPremDocsURL
} from '../../packages/ui/src/utils/docsURL';
import {
  labelFindExplanationsAndExamples,
  labelHere,
  labelNeedHelpWithSearchBarUsage
} from '../../www/front_src/src/Resources/translatedLabels';
import { expect, test } from './fixtures';

const storyId = 'Filter/SearchHelp/Default';

test.use({ viewport: { height: 1000, width: 1200 } });

const platforms = [
  {
    docsUrl: getOnPremDocsURL({ majorVersion: '23', minorVersion: '10' }),
    isCloudPlatform: false,
    name: 'on-premise'
  },
  { docsUrl: cloudDocsURL, isCloudPlatform: true, name: 'cloud' }
];

for (const { docsUrl, isCloudPlatform, name } of platforms) {
  test(`links the ${name} documentation from the help tooltip`, async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    const component = await mount(storyId, { isCloudPlatform });

    await component.getByLabel('Search help').click();

    // MUI tooltips are portalled: query them from the page.
    const tooltip = page.getByRole('tooltip');
    await expect(tooltip).toContainText(labelNeedHelpWithSearchBarUsage);
    await expect(tooltip).toContainText(labelFindExplanationsAndExamples);
    await expect(
      tooltip.getByRole('link', { name: labelHere })
    ).toHaveAttribute('href', docsUrl);

    await expectThemedScreenshot(tooltip, `search-help-${name}`);
  });
}
