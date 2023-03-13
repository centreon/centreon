import { useTranslation } from 'react-i18next';

import { Box, Link, Typography } from '@mui/material';

import NamesList from '../NamesList';
import {
  labelAndManyOthers,
  labelCentreonsGithub,
  labelYouCanSeeFullListOnThe
} from '../translatedLabels';

export const contributors = [
  'Loïc Laurent',
  'Jiliang Wang',
  'Etienne Gautier',
  'Samir Brizini',
  'Thi Uyên Dang',
  'Thomas Untoja',
  'Charles Gautier',
  'Luiz Gustavo Costa',
  'Eric Coquard',
  'Simon Bomm',
  'Fabien Thepaut',
  'Loïc Fontaine',
  'Benjamin Robert',
  'Louis Sautier',
  'btassite',
  'Luiz Felipe Aranha',
  'Lionel Assepo',
  'Matthieu Kermagoret',
  'Victor Vassilev',
  'Valentin Hristov',
  'Sylvestre Gallon',
  'Danijel Halupka',
  'uncleflo',
  'Marie Gallardo',
  'Cédric Meschin',
  'UrBnW',
  'Remi Werquin',
  'Samuel Mutel',
  'Sebastien Boulianne',
  'Guillaume Watteeux',
  'Ira Janssen',
  'SuL',
  'Colin Gagnaire',
  'Lotfi Zaouche',
  'Stéphane Chapron',
  'Hamza Yahiaoui',
  'El Mahdi Abbassi',
  'Mohamed El Meziani',
  'Nouha Al Abrouki',
  'Yassir Ben Boubker'
];

const Contributors = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Box>
      <NamesList columns={3} names={contributors} />
      <Typography>{t(labelAndManyOthers)}</Typography>
      <Typography>
        {t(labelYouCanSeeFullListOnThe)}{' '}
        <Link
          aria-label={t(labelCentreonsGithub)}
          href="https://github.com/centreon/centreon/graphs/contributors"
          rel="noreferrer noopener"
          target="_blank"
          underline="hover"
        >
          {t(labelCentreonsGithub)}
        </Link>
      </Typography>
    </Box>
  );
};

export default Contributors;
