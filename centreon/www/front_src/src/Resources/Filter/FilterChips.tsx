import CloseIcon from '@mui/icons-material/Close';
import { alpha } from '@mui/material/styles';

import { useAtomValue, useSetAtom } from 'jotai';
import pluralize from 'pluralize';
import { isEmpty, isNil, toLower } from 'ramda';
import { useLayoutEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import {
  labelClearFilter,
  labelFilters,
  labelShowLess
} from '../translatedLabels';
import { criteriaNameToQueryLanguageName } from './Criterias/searchQueryLanguage/models';
import {
  filterWithParsedSearchDerivedAtom,
  setCriteriaAndNewFilterDerivedAtom
} from './filterAtoms';

const nonChipCriterias = ['search', 'sort'];

// Width reserved on the right of the collapsed bar for the "+N filters" button.
const moreButtonReserve = 104;

const useStyles = makeStyles()((theme) => ({
  chip: {
    alignItems: 'center',
    backgroundColor: alpha(theme.palette.primary.main, 0.08),
    border: `1px solid ${alpha(theme.palette.primary.main, 0.18)}`,
    borderRadius: theme.spacing(2),
    color: theme.palette.text.primary,
    display: 'inline-flex',
    flex: 'none',
    fontFamily: 'ui-monospace, "Roboto Mono", Menlo, monospace',
    fontSize: theme.typography.caption.fontSize,
    gap: theme.spacing(0.25),
    height: theme.spacing(3.25),
    paddingLeft: theme.spacing(1.25),
    paddingRight: theme.spacing(0.5),
    whiteSpace: 'nowrap'
  },
  close: {
    '&:hover': {
      opacity: 1
    },
    alignItems: 'center',
    background: 'transparent',
    border: 0,
    color: 'inherit',
    cursor: 'pointer',
    display: 'inline-flex',
    fontSize: theme.spacing(1.75),
    justifyContent: 'center',
    marginLeft: theme.spacing(0.5),
    opacity: 0.55,
    padding: 0
  },
  container: {
    alignItems: 'center',
    display: 'flex',
    flexWrap: 'wrap',
    gap: theme.spacing(0.75),
    paddingTop: theme.spacing(0.75)
  },
  expanded: {
    flex: 1,
    flexWrap: 'wrap',
    minWidth: 0,
    paddingTop: 0
  },
  inline: {
    flex: 1,
    flexWrap: 'nowrap',
    minWidth: 0,
    overflow: 'hidden',
    paddingTop: 0,
    position: 'relative'
  },
  key: {
    color: theme.palette.primary.main,
    fontWeight: theme.typography.fontWeightBold
  },
  less: {
    '&:hover': {
      color: theme.palette.primary.main
    },
    alignSelf: 'center',
    background: 'transparent',
    border: 0,
    color: theme.palette.text.secondary,
    cursor: 'pointer',
    fontSize: theme.typography.caption.fontSize,
    fontWeight: theme.typography.fontWeightBold,
    paddingInline: theme.spacing(0.5),
    whiteSpace: 'nowrap'
  },
  more: {
    '&:hover': {
      color: theme.palette.primary.main
    },
    alignItems: 'center',
    background: `linear-gradient(to right, transparent, ${theme.palette.background.paper} ${theme.spacing(1.5)})`,
    border: 0,
    bottom: 0,
    color: theme.palette.text.secondary,
    cursor: 'pointer',
    display: 'inline-flex',
    fontSize: theme.typography.caption.fontSize,
    fontWeight: theme.typography.fontWeightBold,
    paddingLeft: theme.spacing(2),
    paddingRight: theme.spacing(0.5),
    position: 'absolute',
    right: 0,
    top: 0,
    whiteSpace: 'nowrap'
  },
  value: {
    color: theme.palette.text.secondary
  }
}));

interface ChipModel {
  criteriaName: string;
  keyLabel: string;
  valueId: number | string;
  valueLabel: string;
}

const getKeyLabel = (criteriaName: string): string => {
  const singular = pluralize.singular(criteriaName);

  return criteriaNameToQueryLanguageName[singular] || singular;
};

const getValueLabel = (valueId: string): string =>
  criteriaNameToQueryLanguageName[valueId] || toLower(valueId);

const computeChips = (criterias): Array<ChipModel> =>
  (criterias || [])
    .filter(
      (criteria) =>
        !nonChipCriterias.includes(criteria.name) &&
        Array.isArray(criteria.value) &&
        !isEmpty(criteria.value)
    )
    .flatMap((criteria) => {
      const keyLabel = getKeyLabel(criteria.name);
      const isDynamic = !isNil(criteria.object_type);

      return (criteria.value as Array<{ id: number | string; name: string }>)
        .filter((entry) => !isNil(entry?.id))
        .map((entry) => ({
          criteriaName: criteria.name,
          keyLabel,
          valueId: entry.id,
          valueLabel: isDynamic ? entry.name : getValueLabel(String(entry.id))
        }));
    });

export const useActiveFilterChips = (): Array<ChipModel> => {
  const filterWithParsedSearch = useAtomValue(
    filterWithParsedSearchDerivedAtom
  );

  return computeChips(filterWithParsedSearch.criterias);
};

interface Props {
  expanded?: boolean;
  inline?: boolean;
  onToggleExpand?: () => void;
}

const FilterChips = ({
  inline = false,
  expanded = false,
  onToggleExpand
}: Props): JSX.Element | null => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();

  const containerRef = useRef<HTMLDivElement>(null);
  const [hiddenCount, setHiddenCount] = useState(0);

  const filterWithParsedSearch = useAtomValue(
    filterWithParsedSearchDerivedAtom
  );
  const setCriteriaAndNewFilter = useSetAtom(
    setCriteriaAndNewFilterDerivedAtom
  );

  const chips = computeChips(filterWithParsedSearch.criterias);
  const chipsSignature = chips
    .map(({ criteriaName, valueId }) => `${criteriaName}:${valueId}`)
    .join('|');

  const measureChips = inline && !expanded;

  useLayoutEffect(() => {
    if (!measureChips) {
      return undefined;
    }

    const container = containerRef.current;
    if (!container) {
      return undefined;
    }

    const measure = (): void => {
      const limit = container.clientWidth - moreButtonReserve;
      const chipNodes =
        container.querySelectorAll<HTMLElement>('[data-chip="true"]');

      let hidden = 0;
      chipNodes.forEach((node) => {
        if (node.offsetLeft + node.offsetWidth > limit) {
          hidden += 1;
        }
      });

      setHiddenCount(hidden);
    };

    measure();
    const observer = new ResizeObserver(measure);
    observer.observe(container);

    return () => observer.disconnect();
  }, [measureChips, chipsSignature]);

  if (isEmpty(chips)) {
    return null;
  }

  const removeValue = (
    criteriaName: string,
    valueId: number | string
  ): void => {
    const criteria = filterWithParsedSearch.criterias.find(
      ({ name }) => name === criteriaName
    );

    if (isNil(criteria)) {
      return;
    }

    const value = (criteria.value as Array<{ id: number | string }>).filter(
      (entry) => entry.id !== valueId
    );

    setCriteriaAndNewFilter({ apply: true, name: criteriaName, value });
  };

  const renderChip = ({
    criteriaName,
    keyLabel,
    valueId,
    valueLabel
  }: ChipModel): JSX.Element => {
    const label = `${keyLabel}:${valueLabel}`;

    return (
      <span
        className={classes.chip}
        data-chip="true"
        key={`${criteriaName}-${valueId}`}
      >
        <span className={classes.key}>{keyLabel}:</span>
        <span className={classes.value}>{valueLabel}</span>
        <button
          aria-label={`${t(labelClearFilter)} ${label}`}
          className={classes.close}
          data-testid={`removeChip-${label}`}
          onClick={(): void => removeValue(criteriaName, valueId)}
          type="button"
        >
          <CloseIcon fontSize="inherit" />
        </button>
      </span>
    );
  };

  return (
    <div
      className={cx(classes.container, {
        [classes.inline]: inline && !expanded,
        [classes.expanded]: inline && expanded
      })}
      data-testid="filterChips"
      ref={containerRef}
    >
      {chips.map(renderChip)}
      {inline && !expanded && hiddenCount > 0 && (
        <button
          className={classes.more}
          data-testid="filterChipsMore"
          onClick={onToggleExpand}
          type="button"
        >
          +{hiddenCount} {t(labelFilters)}
        </button>
      )}
      {inline && expanded && (
        <button
          className={classes.less}
          data-testid="filterChipsLess"
          onClick={onToggleExpand}
          type="button"
        >
          {t(labelShowLess)}
        </button>
      )}
    </div>
  );
};

export default FilterChips;
