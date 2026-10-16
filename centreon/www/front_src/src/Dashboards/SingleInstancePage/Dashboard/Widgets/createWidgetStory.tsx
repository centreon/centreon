import type { ComponentType } from 'react';

/** Serializable props of a dashboard widget story. */
export interface WidgetStoryArgs {
  panelData?: Record<string, unknown>;
  panelOptions?: Record<string, unknown> | null;
}

/**
 * Builds the story of a dashboard widget, mounted with the props the Cypress
 * specs used; specs pass the panel data and options.
 */
export const createWidgetStory =
  (Widget: ComponentType<never>) =>
  ({ panelData = {}, panelOptions = null }: WidgetStoryArgs): JSX.Element => {
    const AnyWidget = Widget as ComponentType<Record<string, unknown>>;

    return (
      <AnyWidget
        dashboardId={1}
        globalRefreshInterval={{ interval: null, type: 'global' }}
        hasDescription={false}
        id="1"
        isInViewport
        panelData={panelData}
        panelOptions={panelOptions}
        refreshCount={0}
        widgetPrefixQuery="widget"
      />
    );
  };
