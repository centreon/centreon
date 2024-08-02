import axios, { AxiosResponse, CancelToken } from 'axios';
import { equals } from 'ramda';

import { Resource, ResourceCategory } from '../../models';
import { AcknowledgeFormValues } from '../Acknowledge';
import { DowntimeToPost } from '../Downtime';

import { acknowledgeEndpoint, downtimeEndpoint } from './endpoint';

interface ResourcesWithAcknowledgeParams {
  cancelToken: CancelToken;
  params: AcknowledgeFormValues;
  resources: Array<Resource>;
}

const acknowledgeResources =
  (cancelToken: CancelToken) =>
  ({
    resources,
    params
  }: ResourcesWithAcknowledgeParams): Promise<Array<AxiosResponse>> => {
    const payload = resources.map(({ type, id, parent, service_id }) => ({
      id: equals(type, 'anomaly-detection') ? service_id : id,
      parent: parent ? { id: parent?.id } : null,
      type: ResourceCategory[type]
    }));

    return axios.post(
      acknowledgeEndpoint,
      {
        acknowledgement: {
          comment: params.comment,
          is_notify_contacts: params.notify,
          is_persistent_comment: params.persistent,
          is_sticky: params.isSticky,
          with_services: params.acknowledgeAttachedResources
        },
        resources: payload
      },
      { cancelToken }
    );
  };

interface ResourcesWithDowntimeParams {
  params: DowntimeToPost;
  resources: Array<Resource>;
}

const setDowntimeOnResources =
  (cancelToken: CancelToken) =>
  ({
    resources,
    params
  }: ResourcesWithDowntimeParams): Promise<AxiosResponse> => {
    const payload = resources.map(({ type, id, parent, service_id }) => ({
      id: equals(type, 'anomaly-detection') ? service_id : id,
      parent: parent ? { id: parent?.id } : null,
      type: ResourceCategory[type]
    }));

    return axios.post(
      downtimeEndpoint,
      {
        downtime: {
          comment: params.comment,
          duration: params.duration,
          end_time: params.endTime,
          is_fixed: params.fixed,
          start_time: params.startTime,
          with_services: params.isDowntimeWithServices
        },
        resources: payload
      },
      { cancelToken }
    );
  };

export interface CommentParameters {
  comment: string;
  date: string;
}

export { acknowledgeResources, setDowntimeOnResources };
