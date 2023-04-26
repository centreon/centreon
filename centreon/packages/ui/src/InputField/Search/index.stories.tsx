import RegexpHelpTooltip from './RegexpHelpTooltip';
import PersistentTooltip from './PersistentTooltip';

import SearchInput from '.';

export default { title: 'InputField/Search' };

export const normal = (): JSX.Element => <SearchInput />;

export const label = (): JSX.Element => <SearchInput label="Search" />;

const tooltipDescription = (
  <>
    <p>Here is how you can use this search input</p>
    <p>Just type something</p>
  </>
);

const tooltipExamples = [
  <li key="first">First example</li>,
  <li key="second">Second example</li>,
  <li key="third">Third example</li>
];

const RegexpHelp = (): JSX.Element => (
  <RegexpHelpTooltip
    description={tooltipDescription}
    examples={tooltipExamples}
    labelExamples="Here are some examples"
    labelGetHelp="This link should help you"
    labelSearchHelp="search input"
    labelTips="Tips"
    labelUrlTip="regex101.com"
    urlTip="https://regex101.com"
  />
);

export const searchInputWithRegexpHelpTooltip = (): JSX.Element => (
  <SearchInput
    EndAdornment={(): JSX.Element => <RegexpHelp />}
    placeholder="Search"
  />
);

const PersistentHelpTooltip = (props): JSX.Element => (
  <PersistentTooltip labelSearchHelp="search input" {...props}>
    <div>
      <p>Here is a simple persistent </p>
      <p>With a description about the input</p>
    </div>
  </PersistentTooltip>
);

export const searchInputWithPersistentHelpTooltip = (): JSX.Element => (
  <SearchInput
    EndAdornment={(): JSX.Element => <PersistentHelpTooltip />}
    placeholder="Search"
  />
);

export const searchInputWithOpenedPersistentHelpTooltip = (): JSX.Element => (
  <SearchInput
    EndAdornment={(): JSX.Element => <PersistentHelpTooltip openTooltip />}
    placeholder="Search"
  />
);
