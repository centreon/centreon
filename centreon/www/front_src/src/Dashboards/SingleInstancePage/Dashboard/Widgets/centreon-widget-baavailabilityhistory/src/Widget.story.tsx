import Widget from '.';

interface Props {
  resources?: Array<unknown>;
}

export const Default = ({ resources = [] }: Props): JSX.Element => (
  <Widget
    dashboardId={1}
    globalRefreshInterval={{ interval: null, type: 'global' }}
    hasDescription={false}
    id="1"
    isInViewport
    panelData={{ resources } as never}
    panelOptions={null as never}
    refreshCount={0}
    widgetPrefixQuery="widget"
  />
);
