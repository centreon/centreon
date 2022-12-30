import { isNil } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { List, ListItem, Slide, Paper } from '@mui/material';
import ForwardIcon from '@mui/icons-material/ArrowForwardIos';

import Panel from '..';
import ContentWithCircularLoading from '../../ContentWithCircularProgress';
import useMemoComponent from '../../utils/useMemoComponent';

import ExpandableSection from './ExpandableSection';

const panelWidth = 550;
const closeSecondaryPanelBarWidth = 20;

interface GridTemplateColumns {
  hasSecondaryPanel?: boolean;
  mainPanelWidth: number;
}

const getGridTemplateColumns = ({
  mainPanelWidth,
  hasSecondaryPanel
}: GridTemplateColumns): string => {
  if (!hasSecondaryPanel) {
    return '100%';
  }
  if (!mainPanelWidth) {
    return `1fr ${closeSecondaryPanelBarWidth}px 1fr`;
  }

  return `${mainPanelWidth}px ${closeSecondaryPanelBarWidth}px 1fr`;
};

interface StylesProps {
  hasSecondaryPanel?: boolean;
  mainPanelWidth: number;
}

const useStyles = makeStyles<StylesProps>()(
  (theme, { hasSecondaryPanel, mainPanelWidth }) => ({
    closeIcon: {
      margin: 'auto',
      width: 15
    },
    closeSecondaryPanelBar: {
      alignContent: 'center',
      alignItems: 'center',
      backgroundColor: theme.palette.background.paper,
      borderBottom: 'none',
      borderTop: 'none',
      cursor: 'pointer',
      display: 'flex',
      width: closeSecondaryPanelBarWidth
    },
    container: {
      display: hasSecondaryPanel ? 'grid' : 'block',
      gridTemplateColumns: getGridTemplateColumns({
        hasSecondaryPanel,
        mainPanelWidth
      }),
      height: '100%'
    },
    mainPanel: {
      bottom: 0,
      left: 0,
      overflow: 'auto',
      position: hasSecondaryPanel ? 'unset' : 'absolute',
      right: 0,
      top: 0,
      width: mainPanelWidth
    }
  })
);

interface Section {
  expandable: boolean;
  id: string;
  section: JSX.Element;
  title?: string;
}

interface SectionPanelProps {
  header: JSX.Element;
  loading?: boolean;
  mainPanelWidth?: number;
  onClose: () => void;
  onResize?: (width: number) => void;
  onSecondaryPanelClose?: () => void;
  secondaryPanel?: JSX.Element;
  sections: Array<Section>;
  width?: (width: number) => void;
}

const SectionPanel = ({
  header,
  secondaryPanel,
  sections,
  onSecondaryPanelClose = (): undefined => undefined,
  onClose = (): undefined => undefined,
  onResize,
  loading = false,
  mainPanelWidth = panelWidth,
  width
}: SectionPanelProps): JSX.Element => {
  const hasSecondaryPanel = !isNil(secondaryPanel);

  const { classes } = useStyles({
    hasSecondaryPanel,
    mainPanelWidth
  });

  const getWidth = (): number => {
    if (hasSecondaryPanel) {
      const newWidth = panelWidth * 2 + closeSecondaryPanelBarWidth;
      width?.(newWidth);

      return newWidth;
    }
    width?.(panelWidth);

    return panelWidth;
  };

  return (
    <Panel
      header={header}
      selectedTab={
        <ContentWithCircularLoading alignCenter loading={loading}>
          <div className={classes.container}>
            <List className={classes.mainPanel}>
              {sections.map(({ id, title, section, expandable }) =>
                expandable ? (
                  <ExpandableSection key={id} title={title as string}>
                    {section}
                  </ExpandableSection>
                ) : (
                  <ListItem key={id}>{section}</ListItem>
                )
              )}
            </List>

            {hasSecondaryPanel && (
              <Paper
                square
                aria-label="Close Secondary Panel"
                className={classes.closeSecondaryPanelBar}
                onClick={onSecondaryPanelClose}
              >
                <ForwardIcon className={classes.closeIcon} color="primary" />
              </Paper>
            )}

            <Slide
              direction="left"
              in={hasSecondaryPanel}
              timeout={{ enter: 150, exit: 50 }}
            >
              <div>{secondaryPanel}</div>
            </Slide>
          </div>
        </ContentWithCircularLoading>
      }
      width={getWidth()}
      onClose={onClose}
      onResize={onResize}
    />
  );
};

interface MemoizedSectionPanelProps extends SectionPanelProps {
  memoProps?: Array<unknown>;
}

export const MemoizedSectionPanel = ({
  memoProps = [],
  sections,
  secondaryPanel,
  loading,
  ...props
}: MemoizedSectionPanelProps): JSX.Element =>
  useMemoComponent({
    Component: (
      <SectionPanel
        loading={loading}
        secondaryPanel={secondaryPanel}
        sections={sections}
        {...props}
      />
    ),
    memoProps: [...memoProps, sections, secondaryPanel, loading]
  });

export default SectionPanel;
