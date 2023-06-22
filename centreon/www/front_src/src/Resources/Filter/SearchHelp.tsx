import { useState } from 'react';

import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { Link, Box } from '@mui/material';

import { PersistentTooltip } from '@centreon/ui';

import { platformVersionsAtom } from '../../Main/atoms/platformVersionsAtom';
import {
  labelHowToUseTheSearchbar,
  labelSearchHelp
} from '../translatedLabels';

const useStyles = makeStyles()((theme) => ({
  container: {
    margin: theme.spacing(0.75, 0),
    marginRight: theme.spacing(2)
  },
  link: {
    color: theme.palette.primary.main,
    textDecoration: 'none'
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
        <Link
          className={classes.link}
          href={docsURL}
          rel="noreferrer"
          target="_blank"
          onClick={() => setOpenTooltip(false)}
        >
          {t(labelHowToUseTheSearchbar)}
        </Link>
      </Box>
    </PersistentTooltip>
  );
};

export default SearchHelp;
