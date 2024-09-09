import { useAtomValue } from 'jotai';
import { isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { SelectEntry, Subtitle } from '../../../..';
import { List as UIList } from '../../../List';
import { valuesAtom } from '../atoms';
import { Labels } from '../models';

import Item from './Item';
import { useListStyles } from './List.styles';

interface Props {
  labels: Labels['list'];
  roles: Array<SelectEntry>;
}

const List = ({ labels, roles }: Props): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useListStyles();
  const values = useAtomValue(valuesAtom);

  return (
    <div>
      <Subtitle>{t(labels.title)}</Subtitle>
      <div className={classes.list}>
        {isEmpty(values) ? (
          <Typography sx={{ py: 2 }} textAlign="center">
            {t(labels.empty)}
          </Typography>
        ) : (
          <UIList>
            {values.map(({ id, ...rest }, index) => (
              <Item
                id={id}
                key={id}
                labels={labels}
                {...rest}
                index={index}
                roles={roles}
              />
            ))}
          </UIList>
        )}
      </div>
    </div>
  );
};

export default List;
