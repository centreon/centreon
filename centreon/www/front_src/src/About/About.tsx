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

export const pendoSlotId = 'about-pendo-slot';

const About = (): ReactElement => {
  const platformVersion = useAtomValue(platformVersionsAtom);

  return (
    <div className="px-4" id="about-page">
      <div className="rounded border border-divider bg-background-paper">
        <Hero version={platformVersion?.web.version} />
        <div className="px-8 pb-1">
          <div className="flex flex-col gap-2 lg:flex-row lg:gap-6">
            <div className="min-w-0 flex-1 pt-1">
              <Row
                id="about-project-and-contributors"
                label={labelProjectAndContributors}
                withTopDivider={false}
              >
                <Credits />
              </Row>
              <Row id="about-security" label={labelSecurity}>
                <SecurityNotice />
              </Row>
              <ResourcesGrid />
            </div>
            {/* Filled by Pendo at runtime. It must stay childless: React would
                wipe any content injected into an element it renders children in.
                Hidden while empty, so the page is unchanged without Pendo. On
                wide screens it spans from the hero down to the resources. */}
            <div
              className="empty:hidden lg:w-1/2 lg:shrink-0"
              id={pendoSlotId}
            />
          </div>
          <Copyright />
        </div>
      </div>
    </div>
  );
};

export default About;
