<<<<<<< HEAD
import mockDate from 'mockdate';
import axios from 'axios';
import { last, pick, map, head } from 'ramda';
import userEvent from '@testing-library/user-event';
import { Provider } from 'jotai';
import dayjs from 'dayjs';

=======
import * as React from 'react';

import mockDate from 'mockdate';
import axios from 'axios';
import { last, pick, map } from 'ramda';
>>>>>>> centreon/dev-21.10.x
import {
  render,
  RenderResult,
  waitFor,
  fireEvent,
  act,
<<<<<<< HEAD
  SeverityCode,
  screen,
} from '@centreon/ui';
import {
  acknowledgementAtom,
  aclAtom,
  downtimeAtom,
  refreshIntervalAtom,
  userAtom,
} from '@centreon/ui-context';
=======
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { useUserContext } from '@centreon/ui-context';
import { SeverityCode } from '@centreon/ui';
>>>>>>> centreon/dev-21.10.x

import {
  labelAcknowledgedBy,
  labelDowntimeBy,
  labelRefresh,
  labelDisableAutorefresh,
  labelEnableAutorefresh,
  labelAcknowledge,
  labelSetDowntime,
  labelSetDowntimeOnServices,
  labelAcknowledgeServices,
  labelNotify,
  labelFixed,
<<<<<<< HEAD
  labelCheck,
=======
  labelChangeEndDate,
  labelCheck,
  labelServicesDenied,
>>>>>>> centreon/dev-21.10.x
  labelHostsDenied,
  labelMoreActions,
  labelDisacknowledge,
  labelDisacknowledgeServices,
  labelSubmitStatus,
  labelUp,
  labelUnreachable,
  labelDown,
  labelOutput,
  labelPerformanceData,
  labelSubmit,
  labelOk,
  labelWarning,
  labelCritical,
  labelUnknown,
  labelAddComment,
<<<<<<< HEAD
  labelEndTime,
  labelEndDateGreaterThanStartDate,
  labelInvalidFormat,
  labelStartTime,
  labelDuration,
} from '../translatedLabels';
import useLoadResources from '../Listing/useLoadResources';
import useListing from '../Listing/useListing';
import useFilter from '../testUtils/useFilter';
import Context, { ResourceContext } from '../testUtils/Context';
import { Resource } from '../models';
import useLoadDetails from '../testUtils/useLoadDetails';
import useDetails from '../Details/useDetails';
import useActions from '../testUtils/useActions';
=======
  labelPersistent,
} from '../translatedLabels';
import useLoadResources from '../Listing/useLoadResources';
import useListing from '../Listing/useListing';
import useFilter from '../Filter/useFilter';
import Context, { ResourceContext } from '../Context';
import { Resource } from '../models';
import useDetails from '../Details/useDetails';
>>>>>>> centreon/dev-21.10.x

import {
  acknowledgeEndpoint,
  downtimeEndpoint,
  checkEndpoint,
} from './api/endpoint';
<<<<<<< HEAD
=======
import useActions from './useActions';
>>>>>>> centreon/dev-21.10.x
import { disacknowledgeEndpoint } from './Resource/Disacknowledge/api';
import { submitStatusEndpoint } from './Resource/SubmitStatus/api';

import Actions from '.';

const mockedAxios = axios as jest.Mocked<typeof axios>;

const onRefresh = jest.fn();

<<<<<<< HEAD
jest.mock('@centreon/ui-context', () =>
  jest.requireActual('centreon-frontend/packages/ui-context'),
);

const mockUser = {
  alias: 'admin',
  isExportButtonEnabled: true,
  locale: 'en',
  timezone: 'Europe/Paris',
};
const mockRefreshInterval = 15;
const mockDowntime = {
  duration: 7200,
  fixed: true,
  with_services: false,
};
const mockAcl = {
  actions: {
    host: {
      acknowledgement: true,
      check: true,
      comment: true,
      disacknowledgement: true,
      downtime: true,
      submit_status: true,
    },
    service: {
      acknowledgement: true,
      check: true,
      comment: true,
      disacknowledgement: true,
      downtime: true,
      submit_status: true,
    },
  },
};
const mockAcknowledgement = {
  force_active_checks: false,
  notify: false,
  persistent: true,
  sticky: true,
  with_services: true,
};
=======
jest.mock('react-redux', () => ({
  ...(jest.requireActual('react-redux') as jest.Mocked<unknown>),
  useSelector: jest.fn(),
}));

const mockUserContext = {
  acknowledgement: {
    persistent: true,
    sticky: false,
  },
  acl: {
    actions: {
      host: {
        acknowledgement: true,
        check: true,
        comment: true,
        disacknowledgement: true,
        downtime: true,
        submit_status: true,
      },
      service: {
        acknowledgement: true,
        check: true,
        comment: true,
        disacknowledgement: true,
        downtime: true,
        submit_status: true,
      },
    },
  },
  alias: 'admin',
  downtime: {
    duration: 7200,
    fixed: true,
    with_services: false,
  },
  locale: 'en',
  name: 'admin',

  refreshInterval: 15,
  timezone: 'Europe/Paris',
};

jest.mock('@centreon/centreon-frontend/packages/ui-context', () => ({
  ...(jest.requireActual('@centreon/ui-context') as jest.Mocked<unknown>),
  useUserContext: jest.fn(),
}));

const mockedUserContext = useUserContext as jest.Mock;
>>>>>>> centreon/dev-21.10.x

jest.mock('../icons/Downtime');

const ActionsWithLoading = (): JSX.Element => {
  useLoadResources();

  return <Actions onRefresh={onRefresh} />;
};

let context: ResourceContext;

const host = {
  id: 0,
  passive_checks: true,
  type: 'host',
} as Resource;

const service = {
  id: 1,
  parent: {
    id: 1,
  },
  passive_checks: true,
  type: 'service',
} as Resource;

const ActionsWithContext = (): JSX.Element => {
<<<<<<< HEAD
  const detailsState = useLoadDetails();
=======
  const detailsState = useDetails();
>>>>>>> centreon/dev-21.10.x
  const listingState = useListing();
  const actionsState = useActions();
  const filterState = useFilter();

<<<<<<< HEAD
  useDetails();

=======
>>>>>>> centreon/dev-21.10.x
  context = {
    ...detailsState,
    ...listingState,
    ...actionsState,
    ...filterState,
  } as ResourceContext;

  return (
    <Context.Provider key="context" value={context}>
      <ActionsWithLoading />
    </Context.Provider>
  );
};

<<<<<<< HEAD
const renderActions = (aclAtions = mockAcl): RenderResult => {
  return render(
    <Provider
      initialValues={[
        [userAtom, mockUser],
        [refreshIntervalAtom, mockRefreshInterval],
        [downtimeAtom, mockDowntime],
        [aclAtom, aclAtions],
        [acknowledgementAtom, mockAcknowledgement],
      ]}
    >
      <ActionsWithContext />
    </Provider>,
  );
=======
const renderActions = (): RenderResult => {
  return render(<ActionsWithContext />);
>>>>>>> centreon/dev-21.10.x
};

describe(Actions, () => {
  const labelAcknowledgedByAdmin = `${labelAcknowledgedBy} admin`;
  const labelDowntimeByAdmin = `${labelDowntimeBy} admin`;

  const mockNow = '2020-01-01';

  beforeEach(() => {
<<<<<<< HEAD
    Object.defineProperty(window, 'matchMedia', {
      value: (query: string): MediaQueryList => ({
        addEventListener: (): void => undefined,

        addListener: (): void => undefined,

        dispatchEvent: (): boolean => true,
        // this is the media query that @material-ui/pickers uses to determine if a device is a desktop device
        matches: query === '(pointer: fine)',
        media: query,
        onchange: (): void => undefined,
        removeEventListener: (): void => undefined,
        removeListener: (): void => undefined,
      }),
      writable: true,
    });

    mockedAxios.post.mockReset();
    mockedAxios.get.mockResolvedValue({
      data: {
        meta: {
          limit: 30,
          page: 1,
          total: 0,
        },
        result: [],
      },
    });

    mockDate.set(mockNow);
    onRefresh.mockReset();
  });

  afterEach(() => {
    // eslint-disable-next-line @typescript-eslint/ban-ts-comment
    // @ts-ignore
    delete window.matchMedia;

    mockDate.reset();
    mockedAxios.get.mockReset();
  });

  it('executes a listing request when the refresh button is clicked', async () => {
    renderActions();
=======
    mockedAxios.get
      .mockResolvedValueOnce({
        data: {
          meta: {
            limit: 30,
            page: 1,
            total: 0,
          },
          result: [],
        },
      })
      .mockResolvedValueOnce({ data: [] });

    mockDate.set(mockNow);

    mockedUserContext.mockReturnValue(mockUserContext);
  });

  afterEach(() => {
    mockDate.reset();
    mockedAxios.get.mockReset();
    mockedAxios.post.mockReset();

    mockedUserContext.mockReset();
  });

  it('executes a listing request when the refresh button is clicked', async () => {
    const { getByLabelText } = renderActions();
>>>>>>> centreon/dev-21.10.x

    await waitFor(() => expect(mockedAxios.get).toHaveBeenCalled());

    mockedAxios.get.mockResolvedValueOnce({ data: {} });
<<<<<<< HEAD

    await waitFor(() => {
      expect(screen.getByLabelText(labelRefresh)).toBeInTheDocument();
    });

    const refreshButton = screen.getByLabelText(labelRefresh);

    await waitFor(() => expect(refreshButton.firstChild).toBeEnabled());

    userEvent.click(refreshButton.firstChild as HTMLElement);
=======
    mockedAxios.post.mockResolvedValueOnce({});

    const refreshButton = getByLabelText(labelRefresh);

    await waitFor(() => expect(refreshButton).toBeEnabled());

    fireEvent.click(refreshButton.firstElementChild as HTMLElement);
>>>>>>> centreon/dev-21.10.x

    expect(onRefresh).toHaveBeenCalled();
  });

  it('swaps autorefresh icon when the icon is clicked', async () => {
    const { getByLabelText } = renderActions();

    await waitFor(() => expect(mockedAxios.get).toHaveBeenCalled());

    fireEvent.click(
      getByLabelText(labelDisableAutorefresh).firstElementChild as HTMLElement,
    );

    expect(getByLabelText(labelEnableAutorefresh)).toBeTruthy();

    fireEvent.click(
      getByLabelText(labelEnableAutorefresh).firstElementChild as HTMLElement,
    );

    expect(getByLabelText(labelDisableAutorefresh)).toBeTruthy();
  });

  it.each([
    [labelAcknowledge, labelAcknowledgedByAdmin, labelAcknowledge],
    [labelSetDowntime, labelDowntimeByAdmin, labelSetDowntime],
  ])(
    'cannot send a %p request when the corresponding action is fired and the comment field is left empty',
    async (labelAction, labelComment, labelConfirmAction) => {
      const { getByText, getAllByText, findByText } = renderActions();

      const selectedResources = [host];

      act(() => {
<<<<<<< HEAD
        context.setSelectedResources?.(selectedResources);
=======
        context.setSelectedResources(selectedResources);
>>>>>>> centreon/dev-21.10.x
      });

      await waitFor(() =>
        expect(context.selectedResources).toEqual(selectedResources),
      );

      fireEvent.click(getByText(labelAction));

      const commentField = await findByText(labelComment);

<<<<<<< HEAD
      userEvent.clear(commentField);

      await waitFor(() =>
        expect(
          last<HTMLElement>(getAllByText(labelConfirmAction)) as HTMLElement,
=======
      fireEvent.change(commentField, {
        target: { value: '' },
      });

      await waitFor(() =>
        expect(
          (last<HTMLElement>(getAllByText(labelConfirmAction)) as HTMLElement)
            .parentElement,
>>>>>>> centreon/dev-21.10.x
        ).toBeDisabled(),
      );
    },
  );

  it('sends an acknowledgement request when Resources are selected and the Ackowledgement action is clicked and confirmed', async () => {
<<<<<<< HEAD
    const { getByText, findByLabelText, getAllByText } = renderActions();
=======
    const { getByText, getByLabelText, findByLabelText, getAllByText } =
      renderActions();
>>>>>>> centreon/dev-21.10.x

    const selectedResources = [host, service];

    act(() => {
<<<<<<< HEAD
      context.setSelectedResources?.(selectedResources);
    });

    await waitFor(() => {
      expect(getByText(labelAcknowledge)).toBeInTheDocument();
=======
      context.setSelectedResources(selectedResources);
>>>>>>> centreon/dev-21.10.x
    });

    fireEvent.click(getByText(labelAcknowledge));

    const notifyCheckbox = await findByLabelText(labelNotify);
<<<<<<< HEAD

    fireEvent.click(notifyCheckbox);
=======
    const persistentCheckbox = await findByLabelText(labelPersistent);

    fireEvent.click(notifyCheckbox);
    fireEvent.click(persistentCheckbox);
    fireEvent.click(getByLabelText(labelAcknowledgeServices));
>>>>>>> centreon/dev-21.10.x

    mockedAxios.get.mockResolvedValueOnce({ data: {} });
    mockedAxios.post.mockResolvedValueOnce({});

    fireEvent.click(last(getAllByText(labelAcknowledge)) as HTMLElement);

    await waitFor(() =>
      expect(mockedAxios.post).toHaveBeenCalledWith(
        acknowledgeEndpoint,
        {
          acknowledgement: {
            comment: labelAcknowledgedByAdmin,
<<<<<<< HEAD
            force_active_checks: false,
            is_notify_contacts: true,
            is_persistent_comment: true,
            is_sticky: true,
=======
            is_notify_contacts: true,
            is_persistent_comment: false,
            is_sticky: false,
>>>>>>> centreon/dev-21.10.x
            with_services: true,
          },

          resources: map(pick(['type', 'id', 'parent']), selectedResources),
        },
        expect.anything(),
      ),
    );
  });

  it('sends a discknowledgement request when Resources are selected and the Disackowledgement action is clicked and confirmed', async () => {
<<<<<<< HEAD
    const { getByLabelText, getAllByText, getByText } = renderActions();
=======
    const { getByTitle, getAllByText, getByText } = renderActions();
>>>>>>> centreon/dev-21.10.x

    const selectedResources = [host];

    act(() => {
<<<<<<< HEAD
      context.setSelectedResources?.(selectedResources);
    });

    await waitFor(() => {
      expect(
        getByLabelText(labelMoreActions).firstChild as HTMLElement,
      ).toBeInTheDocument();
    });

    fireEvent.click(getByLabelText(labelMoreActions).firstChild as HTMLElement);
=======
      context.setSelectedResources(selectedResources);
    });

    fireEvent.click(getByTitle(labelMoreActions).firstChild as HTMLElement);
>>>>>>> centreon/dev-21.10.x

    fireEvent.click(getByText(labelDisacknowledge));

    mockedAxios.delete.mockResolvedValueOnce({});

    fireEvent.click(last(getAllByText(labelDisacknowledge)) as HTMLElement);

    await waitFor(() =>
      expect(mockedAxios.delete).toHaveBeenCalledWith(disacknowledgeEndpoint, {
        cancelToken: expect.anything(),
        data: {
          disacknowledgement: {
            with_services: true,
          },

          resources: map(pick(['type', 'id', 'parent']), selectedResources),
        },
      }),
    );
  });

  it('does not display the "Acknowledge services attached to host" checkbox when only services are selected and the Acknowledge action is clicked', async () => {
    const { getByText, findByText, queryByText } = renderActions();

    const selectedResources = [service];

    act(() => {
<<<<<<< HEAD
      context.setSelectedResources?.(selectedResources);
    });

    await waitFor(() => {
      expect(getByText(labelAcknowledge)).toBeInTheDocument();
=======
      context.setSelectedResources(selectedResources);
>>>>>>> centreon/dev-21.10.x
    });

    fireEvent.click(getByText(labelAcknowledge));

    await findByText(labelAcknowledgedByAdmin);

    expect(queryByText(labelAcknowledgeServices)).toBeNull();
  });

  it('does not display the "Discknowledge services attached to host" checkbox when only services are selected and the Disacknowledge action is clicked', async () => {
<<<<<<< HEAD
    const { getByText, queryByText, getByLabelText } = renderActions();
=======
    const { getByText, queryByText, getByTitle } = renderActions();
>>>>>>> centreon/dev-21.10.x

    const selectedResources = [service];

    act(() => {
<<<<<<< HEAD
      context.setSelectedResources?.(selectedResources);
    });

    await waitFor(() => {
      expect(
        getByLabelText(labelMoreActions).firstChild as HTMLElement,
      ).toBeInTheDocument();
    });

    fireEvent.click(getByLabelText(labelMoreActions).firstChild as HTMLElement);
=======
      context.setSelectedResources(selectedResources);
    });

    fireEvent.click(getByTitle(labelMoreActions).firstChild as HTMLElement);
>>>>>>> centreon/dev-21.10.x

    fireEvent.click(getByText(labelDisacknowledge));

    await waitFor(() => {
      expect(queryByText(labelDisacknowledgeServices)).toBeNull();
    });
  });

  it('cannot send a downtime request when Downtime action is clicked, type is flexible and duration is empty', async () => {
<<<<<<< HEAD
    const { findByText, getAllByText, getByLabelText } = renderActions();
=======
    const { findByText, getAllByText, getByLabelText, getByDisplayValue } =
      renderActions();
>>>>>>> centreon/dev-21.10.x

    const selectedResources = [host];

    act(() => {
<<<<<<< HEAD
      context.setSelectedResources?.(selectedResources);
    });

    await waitFor(() => {
      expect(
        last(getAllByText(labelSetDowntime)) as HTMLElement,
      ).toBeInTheDocument();
=======
      context.setSelectedResources(selectedResources);
>>>>>>> centreon/dev-21.10.x
    });

    fireEvent.click(last(getAllByText(labelSetDowntime)) as HTMLElement);

    await findByText(labelDowntimeByAdmin);

<<<<<<< HEAD
    await waitFor(() => {
      expect(getByLabelText(labelDuration)).toBeInTheDocument();
    });

    fireEvent.click(getByLabelText(labelFixed));
    fireEvent.change(getByLabelText(labelDuration), {
=======
    fireEvent.click(getByLabelText(labelFixed));
    fireEvent.change(getByDisplayValue('7200'), {
>>>>>>> centreon/dev-21.10.x
      target: { value: '' },
    });

    await waitFor(() =>
      expect(
<<<<<<< HEAD
        last(getAllByText(labelSetDowntime)) as HTMLElement,
=======
        (last(getAllByText(labelSetDowntime)) as HTMLElement).parentElement,
>>>>>>> centreon/dev-21.10.x
      ).toBeDisabled(),
    );
  });

  it('cannot send a downtime request when Downtime action is clicked and start date is greater than end date', async () => {
<<<<<<< HEAD
    const { getByLabelText, getAllByText, findByText, getByText } =
=======
    const { container, getByLabelText, getAllByText, findByText } =
>>>>>>> centreon/dev-21.10.x
      renderActions();

    const selectedResources = [host];

    act(() => {
<<<<<<< HEAD
      context.setSelectedResources?.(selectedResources);
    });

    await waitFor(() => {
      expect(
        head(getAllByText(labelSetDowntime)) as HTMLElement,
      ).toBeInTheDocument();
    });

    fireEvent.click(head(getAllByText(labelSetDowntime)) as HTMLElement);

    await findByText(labelDowntimeByAdmin);

    userEvent.clear(getByLabelText(labelEndTime));
    userEvent.type(getByLabelText(labelEndTime), dayjs(mockNow).format('L LT'));

    await waitFor(() =>
      expect(
        last(getAllByText(labelSetDowntime)) as HTMLElement,
      ).toBeDisabled(),
    );

    expect(getByText(labelEndDateGreaterThanStartDate)).toBeInTheDocument();
  });

  it('cannot send a downtime request when the Downtime action is clicked and the input dates have an invalid format', async () => {
    const {
      getByLabelText,
      getAllByText,
      findByText,
      getByText,
      findAllByText,
    } = renderActions();

    const selectedResources = [host];

    act(() => {
      context.setSelectedResources?.(selectedResources);
    });

    await findAllByText(labelSetDowntime);

    fireEvent.click(head(getAllByText(labelSetDowntime)) as HTMLElement);

    await findByText(labelDowntimeByAdmin);

    userEvent.type(getByLabelText(labelStartTime), '{backspace}l');

    await waitFor(() => {
      expect(
        last(getAllByText(labelSetDowntime)) as HTMLElement,
      ).toBeDisabled();
    });

    expect(getByText(labelInvalidFormat)).toBeInTheDocument();

    userEvent.type(getByLabelText(labelStartTime), '{backspace}M');

    await waitFor(() =>
      expect(last(getAllByText(labelSetDowntime)) as HTMLElement).toBeEnabled(),
    );

    userEvent.type(getByLabelText(labelEndTime), 'a');

    await waitFor(() =>
      expect(
        last(getAllByText(labelSetDowntime)) as HTMLElement,
      ).toBeDisabled(),
    );

    expect(getByText(labelInvalidFormat)).toBeInTheDocument();

    userEvent.type(getByLabelText(labelEndTime), '{backspace}');

    await waitFor(() =>
      expect(last(getAllByText(labelSetDowntime)) as HTMLElement).toBeEnabled(),
    );
=======
      context.setSelectedResources(selectedResources);
    });

    await waitFor(() =>
      expect(last(getAllByText(labelSetDowntime))).toBeEnabled(),
    );

    fireEvent.click(last(getAllByText(labelSetDowntime)) as HTMLElement);

    await findByText(labelDowntimeByAdmin);

    // set previous day as end date using left arrow key
    fireEvent.click(getByLabelText(labelChangeEndDate));
    fireEvent.keyDown(container, { code: 37, key: 'ArrowLeft' });
    fireEvent.keyDown(container, { code: 13, key: 'Enter' });

    await waitFor(() =>
      expect(
        (last(getAllByText(labelSetDowntime)) as HTMLElement).parentElement,
      ).toBeDisabled(),
    );
>>>>>>> centreon/dev-21.10.x
  });

  it('sends a downtime request when Resources are selected and the Downtime action is clicked and confirmed', async () => {
    const { findAllByText, getAllByText } = renderActions();

    const selectedResources = [host];

    act(() => {
<<<<<<< HEAD
      context.setSelectedResources?.(selectedResources);
    });

    await waitFor(() => {
      expect(
        last(getAllByText(labelSetDowntime)) as HTMLElement,
      ).toBeInTheDocument();
=======
      context.setSelectedResources(selectedResources);
>>>>>>> centreon/dev-21.10.x
    });

    fireEvent.click(last(getAllByText(labelSetDowntime)) as HTMLElement);

    mockedAxios.get.mockResolvedValueOnce({ data: {} });
<<<<<<< HEAD
    mockedAxios.post.mockResolvedValueOnce({});

    await findAllByText(labelDowntimeByAdmin);

    await waitFor(() => {
      expect(last(getAllByText(labelSetDowntime)) as HTMLElement).toBeEnabled();
    });

=======
    mockedAxios.post.mockResolvedValueOnce({}).mockResolvedValueOnce({});

    await findAllByText(labelDowntimeByAdmin);

>>>>>>> centreon/dev-21.10.x
    fireEvent.click(last(getAllByText(labelSetDowntime)) as HTMLElement);

    await waitFor(() =>
      expect(mockedAxios.post).toHaveBeenCalledWith(
        downtimeEndpoint,
        {
          downtime: {
            comment: labelDowntimeByAdmin,
            duration: 7200,
            end_time: '2020-01-01T02:00:00Z',
            is_fixed: true,
            start_time: '2020-01-01T00:00:00Z',
            with_services: false,
          },
          resources: map(pick(['type', 'id', 'parent']), selectedResources),
        },
        expect.anything(),
      ),
    );
  });

  it('sends a check request when Resources are selected and the Check action is clicked', async () => {
    const { getByText } = renderActions();

    const selectedResources = [host, service];

    act(() => {
<<<<<<< HEAD
      context.setSelectedResources?.(selectedResources);
=======
      context.setSelectedResources(selectedResources);
>>>>>>> centreon/dev-21.10.x
    });

    mockedAxios.get.mockResolvedValueOnce({ data: {} });
    mockedAxios.all.mockResolvedValueOnce([]);
    mockedAxios.post.mockResolvedValueOnce({});

<<<<<<< HEAD
    await waitFor(() => {
      expect(getByText(labelCheck)).toBeInTheDocument();
    });

=======
>>>>>>> centreon/dev-21.10.x
    fireEvent.click(getByText(labelCheck));

    await waitFor(() => {
      expect(mockedAxios.post).toHaveBeenCalledWith(
        checkEndpoint,
        {
          resources: map(pick(['type', 'id', 'parent']), selectedResources),
        },
        expect.anything(),
      );
    });
  });

  it('sends a submit status request when a Resource is selected and the Submit status action is clicked', async () => {
    mockedAxios.post.mockResolvedValueOnce({});

<<<<<<< HEAD
    const { getByText, getByLabelText, getAllByText } = renderActions();

    act(() => {
      context.setSelectedResources?.([service]);
    });

    await waitFor(() => {
      expect(
        getByLabelText(labelMoreActions).firstElementChild as HTMLElement,
      ).toBeInTheDocument();
    });

    fireEvent.click(
      getByLabelText(labelMoreActions).firstElementChild as HTMLElement,
    );

    fireEvent.click(getByText(labelSubmitStatus) as HTMLElement);
=======
    const { getByText, getByLabelText, getByTitle, queryByText } =
      renderActions();

    act(() => {
      context.setSelectedResources([service]);
    });

    fireEvent.click(getByTitle(labelMoreActions).firstChild as HTMLElement);

    fireEvent.click(getByText(labelSubmitStatus));
>>>>>>> centreon/dev-21.10.x

    userEvent.click(getByText(labelOk));

    await waitFor(() => {
      expect(getByText(labelWarning)).toBeInTheDocument();
      expect(getByText(labelCritical)).toBeInTheDocument();
      expect(getByText(labelUnknown)).toBeInTheDocument();
    });

    userEvent.click(getByText(labelWarning));

    const output = 'output';
    const performanceData = 'performance data';

    fireEvent.change(getByLabelText(labelOutput), {
      target: {
        value: output,
      },
    });

    fireEvent.change(getByLabelText(labelPerformanceData), {
      target: {
        value: performanceData,
      },
    });

    fireEvent.click(getByText(labelSubmit));

    await waitFor(() => {
      expect(mockedAxios.post).toHaveBeenCalledWith(
        submitStatusEndpoint,
        {
          resources: [
            {
              ...pick(['type', 'id', 'parent'], service),
              output,
              performance_data: performanceData,
              status: 1,
            },
          ],
        },
        expect.anything(),
      );
    });

<<<<<<< HEAD
    act(() => {
      context.setSelectedResources?.([host]);
    });

    fireEvent.click(
      getByLabelText(labelMoreActions).firstElementChild as HTMLElement,
    );

    fireEvent.click(getAllByText(labelSubmitStatus)[1] as HTMLElement);
=======
    await waitFor(() => {
      expect(queryByText(labelSubmitStatus)).toBeNull();
    });

    act(() => {
      context.setSelectedResources([host]);
    });

    fireEvent.click(getByTitle(labelMoreActions).firstChild as HTMLElement);

    fireEvent.click(getByText(labelSubmitStatus));
>>>>>>> centreon/dev-21.10.x

    userEvent.click(getByText(labelUp));

    await waitFor(() => {
      expect(getByText(labelDown)).toBeInTheDocument();
      expect(getByText(labelUnreachable)).toBeInTheDocument();
    });
  });

  it('cannot execute an action when associated ACL are not sufficient', async () => {
<<<<<<< HEAD
    const { getByText, getByLabelText } = renderActions({
      actions: {
        host: {
          acknowledgement: false,
          check: false,
          comment: false,
          disacknowledgement: false,
          downtime: false,
          submit_status: false,
        },
        service: {
          acknowledgement: false,
          check: false,
          comment: false,
          disacknowledgement: false,
          downtime: false,
          submit_status: false,
=======
    mockedUserContext.mockReset().mockReturnValue({
      ...mockUserContext,
      acl: {
        actions: {
          host: {
            acknowledgement: false,
            check: false,
            comment: false,
            disacknowledgement: false,
            downtime: false,
            submit_status: false,
          },
          service: {
            acknowledgement: false,
            check: false,
            comment: false,
            disacknowledgement: false,
            downtime: false,
            submit_status: false,
          },
>>>>>>> centreon/dev-21.10.x
        },
      },
    });

<<<<<<< HEAD
    const selectedResources = [host, service];

    act(() => {
      context.setSelectedResources?.(selectedResources);
    });

    await waitFor(() => {
      expect(getByText(labelCheck)).toBeDisabled();
      expect(getByText(labelAcknowledge)).toBeDisabled();
      expect(getByText(labelSetDowntime)).toBeDisabled();
    });

    fireEvent.click(getByLabelText(labelMoreActions).firstChild as HTMLElement);
=======
    const { getByText, getByTitle } = renderActions();

    const selectedResources = [host, service];

    act(() => {
      context.setSelectedResources(selectedResources);
    });

    await waitFor(() => {
      expect(getByText(labelCheck).parentElement).toBeDisabled();
      expect(getByText(labelAcknowledge).parentElement).toBeDisabled();
      expect(getByText(labelSetDowntime).parentElement).toBeDisabled();
    });

    fireEvent.click(getByTitle(labelMoreActions).firstChild as HTMLElement);
>>>>>>> centreon/dev-21.10.x

    expect(getByText(labelDisacknowledge)).toHaveAttribute(
      'aria-disabled',
      'true',
    );
    expect(getByText(labelAddComment)).toHaveAttribute('aria-disabled', 'true');
  });

  const cannotDowntimeServicesAcl = {
    actions: {
<<<<<<< HEAD
      ...mockAcl.actions,
      service: {
        ...mockAcl.actions.service,
=======
      ...mockUserContext.acl.actions,
      service: {
        ...mockUserContext.acl.actions.service,
>>>>>>> centreon/dev-21.10.x
        downtime: false,
      },
    },
  };

  const cannotAcknowledgeServicesAcl = {
    actions: {
<<<<<<< HEAD
      ...mockAcl.actions,
      service: {
        ...mockAcl.actions.service,
=======
      ...mockUserContext.acl.actions,
      service: {
        ...mockUserContext.acl.actions.service,
>>>>>>> centreon/dev-21.10.x
        acknowledgement: false,
      },
    },
  };

  const cannotDisacknowledgeServicesAcl = {
    actions: {
<<<<<<< HEAD
      ...mockAcl.actions,
      service: {
        ...mockAcl.actions.service,
=======
      ...mockUserContext.acl.actions,
      service: {
        ...mockUserContext.acl.actions.service,
>>>>>>> centreon/dev-21.10.x
        disacknowledgement: false,
      },
    },
  };

  const cannotDowntimeHostsAcl = {
    actions: {
<<<<<<< HEAD
      ...mockAcl.actions,
      host: {
        ...mockAcl.actions.host,
=======
      ...mockUserContext.acl.actions,
      host: {
        ...mockUserContext.acl.actions.host,
>>>>>>> centreon/dev-21.10.x
        downtime: false,
      },
    },
  };

  const cannotAcknowledgeHostsAcl = {
    actions: {
<<<<<<< HEAD
      ...mockAcl.actions,
      host: {
        ...mockAcl.actions.host,
=======
      ...mockUserContext.acl.actions,
      host: {
        ...mockUserContext.acl.actions.host,
>>>>>>> centreon/dev-21.10.x
        acknowledgement: false,
      },
    },
  };

  const cannotDisacknowledgeHostsAcl = {
    actions: {
<<<<<<< HEAD
      ...mockAcl.actions,
      host: {
        ...mockAcl.actions.host,
=======
      ...mockUserContext.acl.actions,
      host: {
        ...mockUserContext.acl.actions.host,
>>>>>>> centreon/dev-21.10.x
        disacknowledgement: false,
      },
    },
  };

  it.each([
    [
      labelSetDowntime,
      labelSetDowntime,
<<<<<<< HEAD
=======
      labelServicesDenied,
      cannotDowntimeServicesAcl,
    ],
    [
      labelAcknowledge,
      labelAcknowledge,
      labelServicesDenied,
      cannotAcknowledgeServicesAcl,
    ],
    [
      labelSetDowntime,
      labelSetDowntime,
>>>>>>> centreon/dev-21.10.x
      labelHostsDenied,
      cannotDowntimeHostsAcl,
    ],
    [
      labelAcknowledge,
      labelAcknowledge,
      labelHostsDenied,
      cannotAcknowledgeHostsAcl,
    ],
    [
      labelDisacknowledge,
      labelDisacknowledge,
      labelHostsDenied,
      cannotDisacknowledgeHostsAcl,
    ],
  ])(
    'displays a warning message when trying to %p with limited ACL',
    async (_, labelAction, labelAclWarning, acl) => {
<<<<<<< HEAD
      const { getByText, getByLabelText } = renderActions(acl);
=======
      mockedUserContext.mockReset().mockReturnValue({
        ...mockUserContext,
        acl,
      });

      const { getByText, getByTitle } = renderActions();
>>>>>>> centreon/dev-21.10.x

      const selectedResources = [host, service];

      act(() => {
<<<<<<< HEAD
        context.setSelectedResources?.(selectedResources);
      });

      await waitFor(() => {
        expect(
          getByLabelText(labelMoreActions).firstChild as HTMLElement,
        ).toBeInTheDocument();
      });

      fireEvent.click(
        getByLabelText(labelMoreActions).firstChild as HTMLElement,
      );
=======
        context.setSelectedResources(selectedResources);
      });

      fireEvent.click(getByTitle(labelMoreActions).firstChild as HTMLElement);
>>>>>>> centreon/dev-21.10.x

      fireEvent.click(getByText(labelAction));

      await waitFor(() => {
        expect(getByText(labelAclWarning)).toBeInTheDocument();
      });
    },
  );

  it.each([
    [
      labelSetDowntime,
      labelSetDowntime,
      labelSetDowntimeOnServices,
      cannotDowntimeServicesAcl,
    ],
    [
      labelAcknowledge,
      labelAcknowledge,
      labelAcknowledgeServices,
      cannotAcknowledgeServicesAcl,
    ],
    [
      labelDisacknowledge,
      labelDisacknowledge,
      labelDisacknowledgeServices,
      cannotDisacknowledgeServicesAcl,
    ],
  ])(
    'disables services propagation option when trying to %p on hosts when ACL on services are not sufficient',
    async (_, labelAction, labelAppliesOnServices, acl) => {
<<<<<<< HEAD
      const { getByText, getByLabelText } = renderActions(acl);

      act(() => {
        context.setSelectedResources?.([host]);
      });

      fireEvent.click(
        getByLabelText(labelMoreActions).firstChild as HTMLElement,
      );
=======
      mockedUserContext.mockReset().mockReturnValue({
        ...mockUserContext,
        acl,
      });

      const { getByText, getByTitle } = renderActions();

      act(() => {
        context.setSelectedResources([host]);
      });

      fireEvent.click(getByTitle(labelMoreActions).firstChild as HTMLElement);
>>>>>>> centreon/dev-21.10.x

      fireEvent.click(getByText(labelAction));

      await waitFor(() => {
        expect(
          getByText(labelAppliesOnServices).parentElement?.querySelector(
            'input[type="checkbox"]',
          ),
        ).toBeDisabled();
      });
    },
  );

  it('disables the submit status action when one of the following condition is met: ACL are not sufficient, more than one resource is selected, selected resource is not passive', async () => {
<<<<<<< HEAD
    const { getByText, getByLabelText } = renderActions({
      actions: {
        ...mockAcl.actions,
        host: {
          ...mockAcl.actions.host,
          submit_status: false,
=======
    const { getByText, getByTitle } = renderActions();

    mockedUserContext.mockReset().mockReturnValue({
      ...mockUserContext,
      acl: {
        actions: {
          ...mockUserContext.acl.actions,
          host: {
            ...mockUserContext.acl.actions.host,
            submit_status: false,
          },
>>>>>>> centreon/dev-21.10.x
        },
      },
    });

    act(() => {
<<<<<<< HEAD
      context.setSelectedResources?.([host, service]);
    });

    fireEvent.click(getByLabelText(labelMoreActions).firstChild as HTMLElement);
=======
      context.setSelectedResources([host, service]);
    });

    fireEvent.click(getByTitle(labelMoreActions).firstChild as HTMLElement);
>>>>>>> centreon/dev-21.10.x

    await waitFor(() => {
      expect(getByText(labelSubmitStatus)).toHaveAttribute(
        'aria-disabled',
        'true',
      );
    });

    act(() => {
<<<<<<< HEAD
      context.setSelectedResources?.([host]);
=======
      context.setSelectedResources([host]);
>>>>>>> centreon/dev-21.10.x
    });

    await waitFor(() => {
      expect(getByText(labelSubmitStatus)).toHaveAttribute(
        'aria-disabled',
        'true',
      );
    });

    act(() => {
<<<<<<< HEAD
      context.setSelectedResources?.([service]);
    });

    await waitFor(() => {
      expect(getByText(labelSubmitStatus)).not.toHaveAttribute('aria-disabled');
    });

    act(() => {
      context.setSelectedResources?.([{ ...service, passive_checks: false }]);
=======
      context.setSelectedResources([service]);
    });

    await waitFor(() => {
      expect(getByText(labelSubmitStatus)).toHaveAttribute(
        'aria-disabled',
        'false',
      );
    });

    act(() => {
      context.setSelectedResources([{ ...service, passive_checks: false }]);
>>>>>>> centreon/dev-21.10.x
    });

    await waitFor(() => {
      expect(getByText(labelSubmitStatus)).toHaveAttribute(
        'aria-disabled',
        'true',
      );
    });
  });

  it('disables the comment action when the ACL are not sufficient or more than one resource is selected', async () => {
<<<<<<< HEAD
    const { getByText, getByLabelText } = renderActions({
      actions: {
        ...mockAcl.actions,
        host: {
          ...mockAcl.actions.host,
          comment: false,
=======
    const { getByText, getByTitle } = renderActions();

    mockedUserContext.mockReset().mockReturnValue({
      ...mockUserContext,
      acl: {
        actions: {
          ...mockUserContext.acl.actions,
          host: {
            ...mockUserContext.acl.actions.host,
            comment: false,
          },
>>>>>>> centreon/dev-21.10.x
        },
      },
    });

    act(() => {
<<<<<<< HEAD
      context.setSelectedResources?.([host, service]);
    });

    fireEvent.click(getByLabelText(labelMoreActions).firstChild as HTMLElement);
=======
      context.setSelectedResources([host, service]);
    });

    fireEvent.click(getByTitle(labelMoreActions).firstChild as HTMLElement);
>>>>>>> centreon/dev-21.10.x

    await waitFor(() => {
      expect(getByText(labelAddComment)).toHaveAttribute(
        'aria-disabled',
        'true',
      );
    });

    act(() => {
<<<<<<< HEAD
      context.setSelectedResources?.([host]);
=======
      context.setSelectedResources([host]);
>>>>>>> centreon/dev-21.10.x
    });

    await waitFor(() => {
      expect(getByText(labelAddComment)).toHaveAttribute(
        'aria-disabled',
        'true',
      );
    });

    act(() => {
<<<<<<< HEAD
      context.setSelectedResources?.([service]);
    });

    await waitFor(() => {
      expect(getByText(labelAddComment)).not.toHaveAttribute('aria-disabled');
=======
      context.setSelectedResources([service]);
    });

    await waitFor(() => {
      expect(getByText(labelAddComment)).toHaveAttribute(
        'aria-disabled',
        'false',
      );
>>>>>>> centreon/dev-21.10.x
    });
  });

  it('disables the acknowledge action when selected resources have an OK or UP status', async () => {
    const { getByText } = renderActions();

    act(() => {
<<<<<<< HEAD
      context.setSelectedResources?.([
=======
      context.setSelectedResources([
>>>>>>> centreon/dev-21.10.x
        {
          ...host,
          status: {
            name: 'UP',
            severity_code: SeverityCode.Ok,
          },
        },
        {
          ...service,
          status: {
            name: 'OK',
            severity_code: SeverityCode.Ok,
          },
        },
      ]);
    });

    await waitFor(() => {
<<<<<<< HEAD
      expect(getByText(labelAcknowledge)).toBeDisabled();
=======
      expect(getByText(labelAcknowledge).parentElement).toBeDisabled();
>>>>>>> centreon/dev-21.10.x
    });
  });
});
