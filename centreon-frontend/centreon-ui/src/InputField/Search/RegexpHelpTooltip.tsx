import * as React from 'react';

import { ifElse, always, isNil } from 'ramda';

import { Box, Link } from '@material-ui/core';

import PersistentTooltip from './PersistentTooltip';

interface ContentProps {
  description: React.ReactElement | null;
  labelExamples: React.ReactElement | null;
  examples: React.ReactElement | null;
  tips: React.ReactElement | null;
}

const RegexpHelpTooltipContent = ({
  description,
  labelExamples,
  examples,
  tips,
}: ContentProps): JSX.Element => (
  <>
    <Box padding={1}>
      {description}
      {labelExamples}
      {examples}
      {tips}
    </Box>
  </>
);

interface TooltipProps {
  description?: React.ReactElement;
  labelExamples?: string;
  examples?: Array<React.ReactElement>;
  labelTips?: string;
  labelGetHelp?: string;
  urlTip?: string;
  labelUrlTip?: string;
  labelSearchHelp: string;
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
      labelExamples={displayElement(<p>{labelExamples}</p>)(labelExamples)}
      examples={displayElement(<ul>{examples?.map((example) => example)}</ul>)(
        examples,
      )}
      tips={displayElement(
        <i>
          <b>{`${labelTips}: `}</b>
          {`${labelGetHelp} `}
          <Link href={urlTip} target="_blank" rel="noopener noreferrer">
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
