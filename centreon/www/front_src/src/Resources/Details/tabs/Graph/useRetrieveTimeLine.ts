import { type ListingModel, useFetchQuery } from "@centreon/ui";
import { useAtomValue } from "jotai";
import { path } from "ramda";
import { graphOptionsAtom } from "../../../Graph/Performance/ExportableGraphWithTimeline/graphOptionsAtoms";
import { GraphOptionId } from "../../../Graph/Performance/models";
import { buildListTimelineEventsEndpoint } from "../Timeline/api";
import { listTimelineEventsDecoder } from "../Timeline/api/decoders";
import type { TimelineEvent } from "../Timeline/models";

const useRetrieveTimeLine = ({
	timelineEndpoint,
	start,
	end,
	timelineEventsLimit,
}) => {
	const graphOptions = useAtomValue(graphOptionsAtom);

	const displayEventAnnotations = path<boolean>(
		[GraphOptionId.displayEvents, "value"],
		graphOptions,
	);

	const parameters = {
		limit: timelineEventsLimit,
		search: {
			conditions: [
				{
					field: "date",
					values: {
						$gt: start,
						$lt: end,
					},
				},
			],
		},
	};
	const { data } = useFetchQuery<ListingModel<TimelineEvent>>({
		decoder: listTimelineEventsDecoder,
		getEndpoint: () =>
			buildListTimelineEventsEndpoint({
				endpoint: timelineEndpoint,
				parameters,
			}),
		getQueryKey: () => ["timeLineEvents"],
		queryOptions: {
			enabled:
				!!timelineEndpoint && !!displayEventAnnotations && !!start && !!end,

			suspense: false,
		},
	});

	return displayEventAnnotations ? data : [];
};

export default useRetrieveTimeLine;
