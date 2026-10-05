import {
  type InputPropsWithoutGroup,
  InputType,
  type SelectEntry
} from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';
import { number, object, string } from 'yup';

import { hostFormPollersEndpoint } from '../../api/endpoints';
import { namedEntityDecoder } from '../../api/namedEntityDecoders';
import type { NamedEntity } from '../../models';
import {
  labelHostConfiguration,
  labelInvalidAddress,
  labelIpAddress,
  labelMonitoringServer,
  labelName,
  labelNameContainsForbiddenCharacters,
  labelNameMustNotStartWithModule,
  labelRequired,
  labelResolve
} from '../../translatedLabels';
import ResolveAddress from '../ResolveAddress';
import { buildSelector } from '../selector';
import type { FormSection, SectionContext } from './models';

// Uniqueness is not checked here: the server owns it, and a client check goes
// stale the moment someone else creates a host.
const nameMaxLength = 200;
const addressMaxLength = 255;
// The set `CreateHostInput` forbids, character for character. A backslash is
// not among them, so the form must not refuse `C:\temp` either.
const forbiddenNameCharacters = /^[^~!$%^&*"|'<>?,()=]*$/;
// The server spells the reserved prefix `_Module_` or `_Module `.
const moduleNamePrefix = /^_Module[_ ]/;

// An IPv4 or IPv6 address, or a name the poller can resolve. The underscore is
// deliberate: `Assert::HOSTNAME_PATTERN` allows it for NetBIOS and Active
// Directory names, so `srv_01` must reach the API rather than stop here.
const address =
  /^(\d{1,3}(\.\d{1,3}){3}|[\da-fA-F:]+:[\da-fA-F:.]*|\w([\w-]*\w)?(\.\w([\w-]*\w)?)*)$/;

// The resolve route does not exist on cloud.
const getAddressInput = ({
  isCloudPlatform,
  t
}: SectionContext): InputPropsWithoutGroup => {
  const addressInput = {
    dataTestId: 'host-form-address',
    fieldName: 'address',
    label: t(labelIpAddress),
    required: true,
    type: InputType.Text
  };

  if (isCloudPlatform) {
    return addressInput;
  }

  return {
    fieldName: 'address-row',
    grid: {
      className: 'grid-cols-[1fr_auto]',
      columns: [
        addressInput,
        {
          custom: { Component: ResolveAddress },
          dataTestId: 'host-form-address-resolve',
          fieldName: 'address-resolve',
          label: t(labelResolve),
          type: InputType.Custom
        }
      ]
    },
    label: 'host-form-address-row',
    required: true,
    type: InputType.Grid
  };
};

interface HostConfigurationDetail {
  address: string;
  name: string;
  poller: NamedEntity;
}

export const hostConfiguration: FormSection<HostConfigurationDetail> = {
  defaultValues: {
    address: '',
    name: '',
    poller: null
  },
  detailDecoders: {
    address: JsonDecoder.string,
    name: JsonDecoder.string,
    poller: JsonDecoder.object(namedEntityDecoder, 'Poller')
  },
  getInputs: (context) => [
    {
      dataTestId: 'host-form-name',
      fieldName: 'name',
      label: context.t(labelName),
      required: true,
      type: InputType.Text
    },
    getAddressInput(context),
    {
      connectedAutocomplete: buildSelector({
        endpoint: hostFormPollersEndpoint,
        getOptionLabel: (option) => (option as SelectEntry)?.name,
        // The listing filter beside this field carries the same label and
        // reads every poller, where this one reads only the active ones.
        queryKey: 'host-form-poller'
      }),
      dataTestId: 'host-form-poller',
      fieldName: 'poller',
      label: context.t(labelMonitoringServer),
      required: true,
      type: InputType.SingleConnectedAutocomplete
    }
  ],
  getSchema: ({ t }) => ({
    address: string()
      .trim()
      .max(addressMaxLength)
      // Without this an empty address reports itself as invalid rather than
      // as missing, which the name field next to it does not do.
      .matches(address, {
        excludeEmptyString: true,
        message: t(labelInvalidAddress)
      })
      .required(t(labelRequired)),
    // Both fields are trimmed the way the server normalises them, so blanks
    // report as missing instead of passing to a 422.
    name: string()
      .trim()
      .max(nameMaxLength)
      .matches(forbiddenNameCharacters, t(labelNameContainsForbiddenCharacters))
      .test(
        'is-not-a-module',
        t(labelNameMustNotStartWithModule),
        (value) => !moduleNamePrefix.test(value ?? '')
      )
      .required(t(labelRequired)),
    poller: object({
      id: number().required(t(labelRequired)),
      name: string()
    })
      .nullable()
      .required(t(labelRequired))
  }),
  label: labelHostConfiguration,
  toPayload: (values) => {
    const { name, address, poller } = values as {
      address: string;
      name: string;
      poller: { id: number } | null;
    };

    return {
      // Trimmed as the schema validates them: yup casts before checking the
      // length, so an untrimmed value passes `max` here and fails it server
      // side.
      address: address?.trim(),
      name: name?.trim(),
      poller_id: poller?.id
    };
  }
};
