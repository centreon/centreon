import { platformVersionsAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import type { ReactElement } from 'react';

import Hero from './Hero';
import Row from './Row';
import Copyright from './Sections/Copyright';
import Credits from './Sections/Credits';
import ResourcesGrid from './Sections/ResourcesGrid';
import SecurityNotice from './Sections/SecurityNotice';
import { labelProjectAndContributors, labelSecurity } from './translatedLabels';

const About = (): ReactElement => {
  const platformVersion = useAtomValue(platformVersionsAtom);

  return (
    <div className="px-4">
      <div className="rounded border border-divider bg-background-paper">
        <Hero version={platformVersion?.web.version} />
        <div className="px-8 py-1">
          <Row label={labelProjectAndContributors} withTopDivider={false}>
            <Credits />
          </Row>
          <Row label={labelSecurity}>
            <SecurityNotice />
          </Row>
          <ResourcesGrid />
          <Copyright />
        </div>
      </div>
    </div>
  );
};

export default About;
