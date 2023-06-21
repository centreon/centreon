import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { Container, Box } from '@mui/material';

import { PersistentTooltip } from '@centreon/ui';

import { platformVersionsAtom } from '../../Main/atoms/platformVersionsAtom';
import { labelSearchHelp } from '../translatedLabels';

const useStyles = makeStyles()((theme) => ({
  container: {
    color: theme.palette.common.black,
    margin: theme.spacing(1, 0)
  },
  link: {
    color: theme.palette.primary.main,
    textDecoration: 'none'
  },
  title: {
    marginBottom: theme.spacing(1)
  }
}));

const SearchHelp = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const platform = useAtomValue(platformVersionsAtom);

  const docsURL = `https://docs.centreon.com/docs/${platform?.web.major}.${platform?.web.minor}/alerts-notifications/resources-status/#search-bar`;

  return (
    <PersistentTooltip labelSearchHelp={t(labelSearchHelp)}>
      <Container className={classes.container}>
        <Box className={classes.title}>
          More informations about how to use the searchbar?
        </Box>
        <Box>
          click{' '}
          <a
            className={classes.link}
            href={docsURL}
            rel="noreferrer"
            target="_blank"
          >
            here
          </a>{' '}
          to access the documentation
        </Box>
      </Container>
    </PersistentTooltip>
  );
};

export default SearchHelp;
