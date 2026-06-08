import { platformVersionsAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';

import AboutFooter from './Card/AboutFooter';
import GetMore from './Card/GetMore';
import Hero from './Card/Hero';
import ProjectAndContributors from './Card/ProjectAndContributors';
import SectionRow from './Card/SectionRow';
import Security from './Card/Security';
import UpsellBanner from './Card/UpsellBanner';
import { labelProjectAndContributors, labelSecurity } from './translatedLabels';

const About = (): JSX.Element => {
  const platformVersion = useAtomValue(platformVersionsAtom);

  return (
    <div className="flex justify-center overflow-y-auto bg-background-default p-4 sm:p-6">
      <div className="w-full max-w-[960px] border border-divider bg-background-paper">
        <Hero version={platformVersion?.web.version} />
        <div className="flex flex-col gap-8 p-6 sm:p-9">
          <SectionRow label={labelProjectAndContributors}>
            <ProjectAndContributors />
          </SectionRow>
          <SectionRow label={labelSecurity}>
            <Security />
          </SectionRow>
          <GetMore />
          <UpsellBanner />
          <AboutFooter />
        </div>
      </div>
    </div>
  );
};

export default About;
