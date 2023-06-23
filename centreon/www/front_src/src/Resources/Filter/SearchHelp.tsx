import { useState } from 'react';

import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { Link, Box, Typography } from '@mui/material';

import { PersistentTooltip } from '@centreon/ui';

import { platformVersionsAtom } from '../../Main/atoms/platformVersionsAtom';
import {
  labelFindExplanationsAndExamples,
  labelHere,
  labelNeedHelpWithSearchBarUsage,
  labelSearchHelp
} from '../translatedLabels';

const useStyles = makeStyles()((theme) => ({
  container: {
    color: theme.palette.common.black,
    margin: theme.spacing(1, 0),
    marginRight: theme.spacing(3)
  },
  link: {
    color: theme.palette.primary.main
  },
  title: {
    marginBottom: theme.spacing(0.5)
  }
}));

const SearchHelp = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const [openTooltip, setOpenTooltip] = useState(false);
  const platform = useAtomValue(platformVersionsAtom);

  const docsURL = `https://docs.centreon.com/docs/${platform?.web.major}.${platform?.web.minor}/alerts-notifications/resources-status/#search-bar`;

  return (
    <PersistentTooltip
      closeTooltip={() => setOpenTooltip(false)}
      labelSearchHelp={t(labelSearchHelp)}
      openTooltip={openTooltip}
      toggleTooltip={() => setOpenTooltip((prevState) => !prevState)}
    >
      <Box className={classes.container}>
        <Typography className={classes.title} variant="body2">
          {t(labelNeedHelpWithSearchBarUsage)}
        </Typography>
        <Typography variant="body2">
          {t(labelFindExplanationsAndExamples)}&nbsp;
          <Link
            className={classes.link}
            href={docsURL}
            rel="noreferrer"
            target="_blank"
            onClick={() => setOpenTooltip(false)}
          >
            {t(labelHere)}
          </Link>
        </Typography>
      </Box>
    </PersistentTooltip>
  );
};

export default SearchHelp;
