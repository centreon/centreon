import {
  platformFeaturesAtom,
  platformVersionsAtom
} from '@centreon/ui-context';

import { useAtomValue } from 'jotai';

import Hero from './Hero';
import Row from './Row';
import Copyright from './Sections/Copyright';
import Credits from './Sections/Credits';
import EditionsUpsell from './Sections/EditionsUpsell';
import ResourcesGrid from './Sections/ResourcesGrid';
import SecurityNotice from './Sections/SecurityNotice';
import { labelProjectAndContributors, labelSecurity } from './translatedLabels';

const About = (): JSX.Element => {
  const platformVersion = useAtomValue(platformVersionsAtom);
  const platformFeatures = useAtomValue(platformFeaturesAtom);

  const isCloudPlatform = Boolean(platformFeatures?.isCloudPlatform);

  return (
    <div className="mx-auto max-w-[900px] px-4">
      <div className="max-h-[85vh] overflow-hidden overflow-y-auto rounded bg-background-paper shadow">
        <Hero
          showOpenSourceEditionTag={!isCloudPlatform}
          version={platformVersion?.web.version}
        />
        <div className="px-8 py-1">
          <Row label={labelProjectAndContributors} withTopDivider={false}>
            <Credits />
          </Row>
          <Row label={labelSecurity}>
            <SecurityNotice />
          </Row>
          <ResourcesGrid />
          {!isCloudPlatform && <EditionsUpsell />}
          <Copyright />
        </div>
      </div>
    </div>
  );
};

export default About;
