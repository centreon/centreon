import { screen, waitFor, within } from '@testing-library/react';

import {
  labelActiveChecksEnabled,
  labelAdvanced,
  labelArguments,
  labelAutoRemediation,
  labelCommand,
  labelComments,
  labelContactsToNotify,
  labelCustomMacros,
  labelDataProcessing,
  labelDefault,
  labelDown,
  labelEnableFreshnessCheck,
  labelEventHandlerEnabled,
  labelFlapDetection,
  labelFlapDetectionEnabled,
  labelFreshnessCheck,
  labelGeneralInformation,
  labelHostBasicInformation,
  labelHostCheckOptions,
  labelHostDetails,
  labelHostGroupsTitle,
  labelHostMonitoringProperties,
  labelMonitoringEngineSchedulingOptions,
  labelNo,
  labelNotificationEnabled,
  labelNotifications,
  labelNotifyOn,
  labelParentChildRelations,
  labelPassiveChecksEnabled,
  labelRelations,
  labelSchedulingOptions,
  labelSnmpVersion,
  labelTemplates,
  labelYes
} from '../translatedLabels';
import { initializeFormLayout } from './initialize';

// The widths the panel opens at, narrowest and widest (`ConfigurationBase`).
const narrow = 720;
const wide = 1400;

const sections = [
  labelGeneralInformation,
  labelHostMonitoringProperties,
  labelRelations,
  labelMonitoringEngineSchedulingOptions,
  labelNotifications,
  labelAdvanced
];

// A field by its test id, the first element carrying it: the wrapper, for a
// text field that carries the id on its input too. The shared autocomplete
// renders no test id: it comes by its label.
type Field = string | HTMLElement;

const getField = (field: Field): HTMLElement =>
  typeof field === 'string' ? screen.getAllByTestId(field)[0] : field;

const getRect = (field: Field): DOMRect =>
  getField(field).getBoundingClientRect();

const getSnmpVersion = (): HTMLElement =>
  screen.getByLabelText(labelSnmpVersion);

const getSectionHeader = (section: string): HTMLElement =>
  screen.getByTestId(`${section}-header`);

// `second` sits right of `first`, on the same row.
const expectBeside = (first: Field, second: Field): void => {
  const firstRect = getRect(first);
  const secondRect = getRect(second);

  expect(secondRect.left).toBeGreaterThanOrEqual(firstRect.right);
  expect(secondRect.top).toBeLessThan(firstRect.bottom);
  expect(firstRect.top).toBeLessThan(secondRect.bottom);
};

// `second` starts below the end of `first`.
const expectBelow = (first: Field, second: Field): void => {
  expect(getRect(second).top).toBeGreaterThanOrEqual(getRect(first).bottom);
};

const follows = (first: Node, second: Node): boolean =>
  Boolean(
    first.compareDocumentPosition(second) & Node.DOCUMENT_POSITION_FOLLOWING
  );

// Shown under the header of `section`, before the next section's.
const expectInSection = (testId: string, section: string): void => {
  const field = getField(testId);
  const nextSection = sections[sections.indexOf(section) + 1];

  expect(follows(getSectionHeader(section), field)).toBe(true);

  if (nextSection) {
    expect(follows(field, getSectionHeader(nextSection))).toBe(true);
  }
};

// The block titles of each section, in the order of the design.
const titles: Record<string, Array<string>> = {
  [labelGeneralInformation]: [labelHostBasicInformation],
  [labelHostMonitoringProperties]: [
    labelHostCheckOptions,
    labelArguments,
    labelTemplates,
    labelCustomMacros
  ],
  [labelRelations]: [labelHostGroupsTitle, labelParentChildRelations],
  [labelMonitoringEngineSchedulingOptions]: [
    labelHostDetails,
    labelSchedulingOptions
  ],
  [labelNotifications]: [
    labelNotifications,
    labelContactsToNotify,
    labelNotifyOn
  ],
  [labelAdvanced]: [
    labelAutoRemediation,
    labelCommand,
    labelArguments,
    labelFreshnessCheck,
    labelDataProcessing,
    labelFlapDetection,
    labelComments
  ]
};

const allTitles = Object.values(titles).flat();

// The titles shown in bold under the header of `section`, before the next
// section's: the field labels carrying the same text are not bold.
const getSectionTitles = (section: string): Array<string | null> => {
  const header = getSectionHeader(section);
  const nextHeader = sections
    .slice(sections.indexOf(section) + 1)
    .map((next) => screen.queryByTestId(`${next}-header`))
    .find((next) => next !== null);

  return screen
    .queryAllByText(
      (content, element) =>
        allTitles.includes(content) &&
        element !== null &&
        getComputedStyle(element).fontWeight === '700'
    )
    .filter(
      (title) =>
        !header.contains(title) &&
        follows(header, title) &&
        (!nextHeader || follows(title, nextHeader))
    )
    .map((title) => title.textContent);
};

// The yes / no / third state fields: their label and the design's word for the
// third state, which differs from field to field.
const triStates: Array<{ label: string; testId: string; third: string }> = [
  {
    label: labelActiveChecksEnabled,
    testId: 'host-form-scheduling-options-activeCheckEnabled',
    third: labelDefault
  },
  {
    label: labelPassiveChecksEnabled,
    testId: 'host-form-scheduling-options-passiveCheckEnabled',
    third: labelDefault
  },
  {
    label: labelNotificationEnabled,
    testId: 'host-form-notifications-enabled',
    third: labelDefault
  },
  {
    label: labelEventHandlerEnabled,
    testId: 'host-form-data-processing-eventHandlerEnabled',
    third: labelDefault
  },
  {
    label: labelEnableFreshnessCheck,
    testId: 'host-form-data-processing-checkFreshness',
    third: labelDefault
  },
  {
    label: labelFlapDetectionEnabled,
    testId: 'host-form-data-processing-flapDetectionEnabled',
    third: labelDefault
  }
];

// The cloud platform keeps the event handler and the freshness check only.
const cloudTriStates = [
  'host-form-data-processing-eventHandlerEnabled',
  'host-form-data-processing-checkFreshness'
];

// The label shown above the buttons, the group's name, and its three options.
const expectTriState = ({
  label,
  testId,
  third
}: (typeof triStates)[number]): void => {
  const group = screen.getByTestId(testId);

  expect(group).toHaveAccessibleName(label);
  // The group sits in a scrolling wrapper, a sibling of the label.
  expect(group.parentElement?.previousElementSibling).toHaveTextContent(
    new RegExp(`^${label}$`)
  );
  expect(
    within(group)
      .getAllByRole('button')
      .map((button) => button.textContent)
  ).toEqual([labelYes, labelNo, third]);
};

const getTabs = (): Array<string | null> =>
  screen.getAllByRole('tab').map((tab) => tab.textContent);

// Every field is rendered once the last section is.
const waitForForm = async (): Promise<void> => {
  await waitFor(() =>
    expect(screen.getAllByTestId(`${labelAdvanced}-header`)).toHaveLength(1)
  );
};

export default () => {
  describe('Layout: ', () => {
    it('shows the six sections, in the order of the design, and a tab for each', async () => {
      initializeFormLayout({ width: wide });

      await waitForForm();

      expect(getTabs()).toEqual(sections);
      sections.slice(1).forEach((section, index) => {
        expect(
          follows(getSectionHeader(sections[index]), getSectionHeader(section))
        ).toBe(true);
      });
    });

    it('shows no Notifications section or tab on a cloud platform', async () => {
      initializeFormLayout({ isCloudPlatform: true, width: wide });

      await waitForForm();

      expect(getTabs()).toEqual(
        sections.filter((section) => section !== labelNotifications)
      );
      expect(
        screen.queryByTestId(`${labelNotifications}-header`)
      ).not.toBeInTheDocument();
    });

    it('shows no empty General Information section or tab in a mass change', async () => {
      initializeFormLayout({ isMassChange: true, width: wide });

      await waitForForm();

      expect(getTabs()).toEqual(
        sections.filter((section) => section !== labelGeneralInformation)
      );
      expect(
        screen.queryByTestId(`${labelGeneralInformation}-header`)
      ).not.toBeInTheDocument();
      // The monitoring server a single host has among its basic information.
      expectInSection(
        'host-form-poller',
        labelMonitoringEngineSchedulingOptions
      );
    });

    it('titles the blocks of each section as the design does', async () => {
      initializeFormLayout({ width: wide });

      await waitForForm();

      sections.forEach((section) => {
        expect(getSectionTitles(section)).toEqual(titles[section]);
      });
    });

    it('shows no title of a block a cloud platform leaves out', async () => {
      initializeFormLayout({ isCloudPlatform: true, width: wide });

      await waitForForm();

      expect(screen.queryByText(labelContactsToNotify)).not.toBeInTheDocument();
      expect(screen.queryByText(labelNotifyOn)).not.toBeInTheDocument();
      sections
        .filter(
          (section) =>
            section !== labelNotifications && section !== labelAdvanced
        )
        .forEach((section) => {
          expect(getSectionTitles(section)).toEqual(titles[section]);
        });
      // No event handler arguments, acknowledgement timeout, flap detection
      // or comment there.
      expect(getSectionTitles(labelAdvanced)).toEqual([
        labelAutoRemediation,
        labelCommand,
        labelFreshnessCheck
      ]);
    });

    it('labels the yes / no / inherited fields and their third option as the design does', async () => {
      for (const isMassChange of [false, true]) {
        const { unmount } = initializeFormLayout({ isMassChange, width: wide });

        await waitForForm();

        triStates.forEach(expectTriState);

        unmount();
      }
    });

    it('labels the yes / no / inherited fields a cloud platform keeps as the design does', async () => {
      initializeFormLayout({ isCloudPlatform: true, width: wide });

      await waitForForm();

      triStates
        .filter(({ testId }) => cloudTriStates.includes(testId))
        .forEach(expectTriState);
      triStates
        .filter(({ testId }) => !cloudTriStates.includes(testId))
        .forEach(({ testId }) => {
          expect(screen.queryByTestId(testId)).not.toBeInTheDocument();
        });
    });

    it('shows the SNMP fields right after the check command and its arguments, before the templates', async () => {
      initializeFormLayout({ width: wide });

      await waitForForm();

      expectInSection(
        'host-form-snmp-community',
        labelHostMonitoringProperties
      );
      expectBeside('host-form-snmp-community', getSnmpVersion());
      expectBelow(
        'host-form-check-options-command',
        'host-form-snmp-community'
      );
      expectBelow('host-form-check-options-args', getSnmpVersion());
      expectBelow('host-form-snmp-community', 'host-form-templates');
      expectBelow(getSnmpVersion(), 'host-form-check-options-macros');
    });

    it('shows the host severity among the relations, below the host categories', async () => {
      initializeFormLayout({ width: wide });

      await waitForForm();

      expectInSection('host-form-severity', labelRelations);
      expectBelow('host-form-categories', 'host-form-severity');
      expectBeside('host-form-groups', 'host-form-categories');
    });

    it('titles the parent and child hosts as one block, below the host groups', async () => {
      initializeFormLayout({ width: narrow });

      await waitForForm();

      const title = screen.getByText(labelParentChildRelations);

      expect(follows(getField('host-form-groups'), title)).toBe(true);
      expect(follows(title, getField('host-form-parent-hosts'))).toBe(true);
      expectBelow('host-form-parent-hosts', 'host-form-child-hosts');
      // Two columns even in the narrowest panel, as designed.
      expectBeside('host-form-groups', 'host-form-categories');
      expect(getRect('host-form-severity').left).toBeGreaterThanOrEqual(
        getRect('host-form-parent-hosts').right
      );
    });

    it('pairs the notification delays row by row', async () => {
      for (const width of [narrow, wide]) {
        const { unmount } = initializeFormLayout({ width });

        await waitForForm();

        expectBeside(
          'host-form-notifications-interval',
          'host-form-notifications-firstDelay'
        );
        expectBeside(
          'host-form-notifications-timeperiod',
          'host-form-notifications-recoveryDelay'
        );
        expectBelow(
          'host-form-notifications-interval',
          'host-form-notifications-timeperiod'
        );

        unmount();
      }
    });

    it('never cuts the Yes / No / Inherit buttons of the scheduling options', async () => {
      const cut: Array<string> = [];

      for (const width of [narrow, 900, 1100, 1250, wide]) {
        const { unmount } = initializeFormLayout({ width });

        await waitForForm();

        for (const testId of [
          'host-form-scheduling-options-activeCheckEnabled',
          'host-form-scheduling-options-passiveCheckEnabled'
        ]) {
          const wrapper = screen.getByTestId(testId)
            .parentElement as HTMLElement;

          // Scrolled means cut: the three buttons (104px each at least) fit.
          if (wrapper.scrollWidth > wrapper.clientWidth) {
            cut.push(`${testId} at ${width}px`);
          }
        }

        unmount();
      }

      expect(cut).toEqual([]);
    });

    it('lays the sections out in columns in the widest panel', async () => {
      initializeFormLayout({ width: wide });

      await waitForForm();

      expectBeside(
        'host-form-check-options-command',
        'host-form-check-options-args'
      );
      expectBeside('host-form-templates', 'host-form-check-options-macros');

      // Host details over two columns, beside the scheduling options over two.
      expectBeside(
        'host-form-extended-infos-note',
        'host-form-extended-infos-actionUrl'
      );
      expectBeside(
        'host-form-extended-infos-actionUrl',
        'host-form-scheduling-options-checkPeriod'
      );
      expectBeside(
        'host-form-scheduling-options-checkPeriod',
        'host-form-scheduling-options-activeCheckEnabled'
      );
      expectBelow(
        'host-form-extended-infos-actionUrl',
        'host-form-extended-infos-geoCoordinates'
      );
      expectBelow(
        'host-form-extended-infos-geoCoordinates',
        'host-form-timezone'
      );
      expect(screen.getByText(labelHostDetails)).toBeVisible();
      expect(screen.getByText(labelSchedulingOptions)).toBeVisible();

      expectBeside(
        'host-form-notifications-enabled',
        `host-form-notifications-options-${labelDown}`
      );

      expectBeside(
        'host-form-data-processing-eventHandlerEnabled',
        'host-form-data-processing-checkFreshness'
      );
      expectBeside(
        'host-form-data-processing-checkFreshness',
        'host-form-data-processing-flapDetectionEnabled'
      );
      expectBelow(
        'host-form-data-processing-freshnessThreshold',
        'host-form-data-processing-acknowledgmentTimeout'
      );
      expectBelow(
        'host-form-data-processing-eventHandlerArgs',
        'host-form-extended-infos-comment'
      );
    });

    it('stacks what the narrowest panel cannot hold side by side', async () => {
      initializeFormLayout({ width: narrow });

      await waitForForm();

      // Still beside each other, as designed.
      expectBeside(
        'host-form-check-options-command',
        'host-form-check-options-args'
      );
      expectBelow('host-form-templates', 'host-form-check-options-macros');

      // Each block in one column, the two blocks still side by side.
      expectBelow(
        'host-form-extended-infos-note',
        'host-form-extended-infos-noteUrl'
      );
      expectBelow(
        'host-form-extended-infos-icon',
        'host-form-extended-infos-actionUrl'
      );
      expectBelow(
        'host-form-extended-infos-geoCoordinates',
        'host-form-timezone'
      );
      expectBeside(
        'host-form-extended-infos-note',
        'host-form-scheduling-options-checkPeriod'
      );
      expectBelow(
        'host-form-scheduling-options-retryCheckInterval',
        'host-form-scheduling-options-activeCheckEnabled'
      );

      expectBelow(
        'host-form-notifications-contacts',
        `host-form-notifications-options-${labelDown}`
      );

      expectBelow(
        'host-form-data-processing-eventHandlerArgs',
        'host-form-data-processing-checkFreshness'
      );
      expectBelow(
        'host-form-data-processing-acknowledgmentTimeout',
        'host-form-data-processing-flapDetectionEnabled'
      );
      expectBelow(
        'host-form-data-processing-highFlapThreshold',
        'host-form-extended-infos-comment'
      );
    });
  });
};
