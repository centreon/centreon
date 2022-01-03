import * as React from 'react';

import { ifElse, always, isNil } from 'ramda';

import { Box, Link } from '@mui/material';

import PersistentTooltip from './PersistentTooltip';

interface ContentProps {
  description: React.ReactElement | null;
  examples: React.ReactElement | null;
  labelExamples: React.ReactElement | null;
  tips: React.ReactElement | null;
}

const RegexpHelpTooltipContent = ({
  description,
  labelExamples,
  examples,
  tips,
}: ContentProps): JSX.Element => (
  <Box padding={1}>
    {description}
    {labelExamples}
    {examples}
    {tips}
  </Box>
);

interface TooltipProps {
  description?: React.ReactElement;
  examples?: Array<React.ReactElement>;
  labelExamples?: string;
  labelGetHelp?: string;
  labelSearchHelp: string;
  labelTips?: string;
  labelUrlTip?: string;
  urlTip?: string;
}

const RegexpHelpTooltip = ({
  description,
  labelSearchHelp,
  labelExamples,
  examples,
  labelTips,
  labelGetHelp,
  urlTip,
  labelUrlTip,
}: TooltipProps): JSX.Element => {
  const displayElement = (element): ((prop) => React.ReactElement | null) =>
    ifElse(isNil, always(null), always(element));

  const content = (
    <RegexpHelpTooltipContent
      description={displayElement(<div>{description}</div>)(description)}
      examples={displayElement(<ul>{examples?.map((example) => example)}</ul>)(
        examples,
      )}
      labelExamples={displayElement(<p>{labelExamples}</p>)(labelExamples)}
      tips={displayElement(
        <i>
          <b>{`${labelTips}: `}</b>
          {`${labelGetHelp} `}
          <Link href={urlTip} rel="noopener noreferrer" target="_blank">
            {labelUrlTip}
          </Link>
        </i>,
      )(labelTips && labelGetHelp && urlTip && labelUrlTip)}
    />
  );

  return (
    <PersistentTooltip labelSearchHelp={labelSearchHelp}>
      {content}
    </PersistentTooltip>
  );
};

export default RegexpHelpTooltip;
