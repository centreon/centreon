import { atom } from "jotai";
import type { Acknowledgement } from ".";
import { defaultAcknowledgement } from "./defaults";

const acknowledgementAtom = atom<Acknowledgement>(defaultAcknowledgement);

export default acknowledgementAtom;
