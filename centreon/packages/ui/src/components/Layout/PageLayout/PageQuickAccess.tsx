import { useTranslation } from 'react-i18next';

import AddIcon from '@mui/icons-material/Add';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import { CircularProgress } from '@mui/material';

import { Button, Menu } from '../..';

import { useStyles } from './PageLayout.styles';
import { labelLoading } from './translatedLabels';

interface NamedEntity {
  id: number | string;
  name: string;
}

type Props = {
  create: () => void;
  elements: Array<NamedEntity>;
  goBack: () => void;
  isActive: (id: number | string) => boolean;
  isLoading?: boolean;
  labels: {
    create: string;
    goBack: string;
  };
  // Observed at the bottom of the list to load the next page when it is reached
  loadMoreRef?: (node: Element | null) => void;
  navigateToElement: (id: number | string) => () => void;
};

export const PageQuickAccess = ({
  elements,
  isActive,
  isLoading,
  loadMoreRef,
  navigateToElement,
  goBack,
  create,
  labels
}: Props): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  return (
    <Menu>
      <Menu.Button data-testid="quickaccess" />
      <Menu.Items>
        <div
          className={classes.quickAccessElements}
          data-testid="quickaccess-elements"
        >
          {elements?.map((element) => (
            <Menu.Item
              key={`${element.id}`}
              onClick={navigateToElement(element.id)}
              {...(isActive(element.id) && {
                isActive: true,
                isDisabled: true
              })}
            >
              {element.name}
            </Menu.Item>
          ))}
          {loadMoreRef && (
            <div className={classes.quickAccessLoadMore} ref={loadMoreRef} />
          )}
          {isLoading && (
            <div className={classes.quickAccessLoading}>
              <CircularProgress aria-label={t(labelLoading)} size={20} />
            </div>
          )}
        </div>
        <Menu.Divider key="divider" />
        <Menu.Item key="create">
          <>
            <Button
              icon={<ArrowBackIcon />}
              iconVariant="start"
              variant="ghost"
              onClick={goBack}
            >
              {t(labels.goBack)}
            </Button>
            <Button
              icon={<AddIcon />}
              iconVariant="start"
              variant="secondary"
              onClick={create}
            >
              {t(labels.create)}
            </Button>
          </>
        </Menu.Item>
      </Menu.Items>
    </Menu>
  );
};
