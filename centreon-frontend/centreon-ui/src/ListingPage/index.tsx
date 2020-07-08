import * as React from 'react';

import { makeStyles, Theme } from '@material-ui/core';
import Filters, { FiltersProps } from './Filters';

const useStyles = (
  slidePanelIntegrated: boolean,
): (() => Record<string, string>) =>
  makeStyles<Theme>((theme) => ({
    page: {
      display: 'grid',
      gridTemplateRows: 'auto 1fr',
      backgroundColor: theme.palette.background.default,
      overflowY: 'hidden',
      height: '100%',
    },
    pageBody: {
      display: 'grid',
      gridTemplateRows: '1fr',
      gridTemplateColumns: '1fr 550px',
    },
    listing: {
      marginLeft: theme.spacing(2),
      marginRight: theme.spacing(2),
      gridArea: slidePanelIntegrated ? '1 / 1 / 1 / 1' : '1 / 1 / 1 / span 2',
      height: '100%',
    },
  }));

interface Props {
  listing: React.ReactElement;
  slidePanel?: React.ReactElement;
  slidePanelOpen: boolean;
  slidePanelIntegrate?: boolean;
}

const cumulativeOffset = (element): number => {
  if (!element || !element.offsetParent) {
    return 0;
  }

  return cumulativeOffset(element.offsetParent) + element.offsetTop;
};

const ListingPage = ({
  listing,
  filtersExpandable,
  labelFiltersIcon,
  filters,
  expandableFilters,
  slidePanel,
  slidePanelOpen,
  slidePanelIntegrate = false,
}: Props & FiltersProps): JSX.Element => {
  const classes = useStyles(slidePanelIntegrate && slidePanelOpen)();
  const pageBody = React.useRef<HTMLDivElement>();
  const [height, setHeight] = React.useState<string>('100%');
  const filterSummaryElement = React.useRef<HTMLDivElement>();

  React.useEffect(() => {
    setHeight(pageBodyHeight());
  }, [pageBody.current]);

  const pageBodyHeight = (): string => {
    return pageBody.current
      ? `calc(100vh - ${cumulativeOffset(pageBody.current)}px - ${Math.floor(
          (filterSummaryElement.current?.clientHeight || 0) / 2,
        )}px)`
      : '100%';
  };

  return (
    <div className={classes.page}>
      <Filters
        filtersExpandable={filtersExpandable}
        labelFiltersIcon={labelFiltersIcon}
        filters={filters}
        expandableFilters={expandableFilters}
        onExpandTransitionFinished={() => {
          setHeight(pageBodyHeight());
        }}
        ref={filterSummaryElement}
      />
      <div
        className={classes.pageBody}
        ref={pageBody as React.RefObject<HTMLDivElement>}
        style={{
          height,
        }}
      >
        {slidePanelOpen && slidePanel}
        <div className={classes.listing}>{listing}</div>
      </div>
    </div>
  );
};

export default ListingPage;
