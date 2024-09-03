import { useMemo, useState } from "react";

import { useAtomValue } from "jotai";
import { isNil } from "ramda";

import type { Interval, ListingModel } from "@centreon/ui";
import { TimePeriods, useRequest } from "@centreon/ui";

import type { TabProps } from "..";
import GraphOptions from "../../../Graph/Performance/ExportableGraphWithTimeline/GraphOptions";
import { updatedGraphIntervalAtom } from "../../../Graph/Performance/ExportableGraphWithTimeline/atoms";
import { listResources } from "../../../Listing/api";
import type { Resource } from "../../../models";
import InfiniteScroll from "../../InfiniteScroll";
import ServiceGraphs from "../Services/Graphs";
import LoadingSkeleton from "../Timeline/LoadingSkeleton";
import type { GraphTimeParameters } from "./models";

const HostGraph = ({ details }: TabProps): JSX.Element => {
	const [graphTimeParameters, setGraphTimeParameters] =
		useState<GraphTimeParameters>();

	const updatedGraphInterval = useAtomValue(updatedGraphIntervalAtom);

	const { sendRequest, sending } = useRequest({
		request: listResources,
	});

	const limit = 6;

	const sendListingRequest = ({
		atPage,
	}: {
		atPage?: number;
	}): Promise<ListingModel<Resource>> => {
		return sendRequest({
			limit,
			onlyWithPerformanceData: true,
			page: atPage,
			resourceTypes: ["service"],
			search: {
				conditions: [
					{
						field: "h.name",
						values: {
							$eq: details?.name,
						},
					},
				],
			},
		});
	};

	const getTimePeriodsParameters = (data: GraphTimeParameters): void => {
		setGraphTimeParameters(data);
	};

	const newGraphInterval = useMemo(() => {
		if (!updatedGraphInterval) {
			return undefined;
		}
		return { end: updatedGraphInterval.end, start: updatedGraphInterval.start };
	}, [updatedGraphInterval?.end, updatedGraphInterval?.start]);

	return (
		<InfiniteScroll<Resource>
			details={details}
			filter={
				<TimePeriods
					adjustTimePeriodData={newGraphInterval}
					getParameters={getTimePeriodsParameters}
					renderExternalComponent={<GraphOptions />}
				/>
			}
			limit={limit}
			loading={sending}
			loadingSkeleton={<LoadingSkeleton />}
			preventReloadWhen={isNil(details)}
			sendListingRequest={sendListingRequest}
		>
			{({ infiniteScrollTriggerRef, entities }): JSX.Element => {
				console.log("am i heeeeeeeeeeeeeeeeere");
				return (
					<ServiceGraphs
						graphTimeParameters={graphTimeParameters}
						infiniteScrollTriggerRef={infiniteScrollTriggerRef}
						services={entities}
					/>
				);
			}}
		</InfiniteScroll>
	);
};

export default HostGraph;
