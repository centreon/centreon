import { useState } from 'react';

import { rectIntersection } from '@dnd-kit/core';
import { rectSortingStrategy } from '@dnd-kit/sortable';
import { useAtom } from 'jotai';
import {
  append,
  difference,
  equals,
  filter,
  find,
  findIndex,
  isEmpty,
  map,
  pluck,
  propEq,
  remove,
  uniq
} from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box } from '@mui/material';
import Grid from '@mui/material/Grid';

import {
  SortableItems,
  useLocaleDateTimeFormat,
  useMemoComponent
} from '@centreon/ui';
import type { RootComponentProps } from '@centreon/ui';

import { ResourceDetails } from '../../../models';
import getDetailCardLines, { DetailCardLine } from '../DetailsCard/cards';
import { detailsCardsAtom } from '../detailsCardsAtom';

import Content from './Content';
import { CardsLayout, ChangeExpandedCardsProps, ExpandAction } from './models';

interface Props {
  details: ResourceDetails;
  panelWidth: number;
}

interface MergeDefaultAndStoredCardsProps {
  defaultCards: Array<string>;
  storedCards: Array<string>;
}

const mergeDefaultAndStoredCards = ({
  defaultCards,
  storedCards
}: MergeDefaultAndStoredCardsProps): Array<string> => {
  const differenceBetweenDefaultAndStoredCards = difference(
    defaultCards,
    storedCards
  );

  return uniq([...storedCards, ...differenceBetweenDefaultAndStoredCards]);
};

const SortableCards = ({ panelWidth, details }: Props): JSX.Element => {
  const { toDateTime } = useLocaleDateTimeFormat();
  const { t } = useTranslation();
  const [expandedCards, setExpandedCards] = useState<Array<string>>([]);

  const [storedDetailsCards, storeDetailsCards] = useAtom(detailsCardsAtom);

  const changeExpandedCards = ({
    action,
    card
  }: ChangeExpandedCardsProps): void => {
    if (equals(action, ExpandAction.add)) {
      setExpandedCards(append(card, expandedCards));

      return;
    }

    const expandedCardIndex = findIndex(equals(card), expandedCards);
    setExpandedCards(remove(expandedCardIndex, 1, expandedCards));
  };

  const allDetailsCards = getDetailCardLines({
    changeExpandedCards,
    details,
    expandedCards,
    t,
    toDateTime
  });

  const allDetailsCardsTitle = pluck('title', allDetailsCards);

  const defaultDetailsCardsLayout = isEmpty(storedDetailsCards)
    ? allDetailsCardsTitle
    : mergeDefaultAndStoredCards({
        defaultCards: allDetailsCardsTitle,
        storedCards: storedDetailsCards
      });

  const cards = map<string, CardsLayout>(
    (title) => ({
      id: title,
      width: panelWidth,
      ...(find(propEq(title, 'title'), allDetailsCards) as DetailCardLine)
    }),
    defaultDetailsCardsLayout
  );

  const displayedCards = filter(
    ({ shouldBeDisplayed }) => shouldBeDisplayed,
    cards
  );

  const RootComponent = ({ children }: RootComponentProps): JSX.Element => (
    <Grid container spacing={1} style={{ width: panelWidth }}>
      {children}
    </Grid>
  );

  const dragEnd = ({ items }): void => {
    storeDetailsCards(items);
  };

  return useMemoComponent({
    Component: (
      <Box>
        <SortableItems<CardsLayout>
          updateSortableItemsOnItemsChange
          Content={Content}
          RootComponent={RootComponent}
          collisionDetection={rectIntersection}
          itemProps={[
            'shouldBeDisplayed',
            'line',
            'xs',
            'isCustomCard',
            'width',
            'title'
          ]}
          items={displayedCards}
          sortingStrategy={rectSortingStrategy}
          onDragEnd={dragEnd}
        />
      </Box>
    ),
    memoProps: [panelWidth, expandedCards, details]
  });
};

export default SortableCards;
