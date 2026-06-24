import argparse
import json
import math
import sys

import neurokit2 as nk
import numpy as np
from scipy.signal import butter, filtfilt, iirnotch


def bandpass_filter(signal, fs, low=0.5, high=40.0, order=4):
    nyq = 0.5 * fs
    high = min(high, nyq - 0.1)
    lowcut = low / nyq
    highcut = high / nyq
    b, a = butter(order, [lowcut, highcut], btype="band")
    return filtfilt(b, a, signal)


def notch_filter(signal, fs, notch_freq=50.0, quality_factor=30.0):
    nyq = fs / 2
    if notch_freq >= nyq:
        return signal

    w0 = notch_freq / nyq
    b, a = iirnotch(w0, quality_factor)
    return filtfilt(b, a, signal)


def finite_or_none(value):
    if value is None:
        return None

    value = float(value)
    if math.isnan(value) or math.isinf(value):
        return None

    return value


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--input-json", required=True)
    parser.add_argument("--sample-rate", type=int, default=250)
    args = parser.parse_args()

    with open(args.input_json, "r", encoding="utf-8") as handle:
        payload = json.load(handle)

    raw_values = payload.get("raw_values", [])
    signal = np.asarray(raw_values, dtype=float)

    if signal.size < max(args.sample_rate * 5, 30):
        raise ValueError("Raw EKG terlalu pendek untuk ekstraksi HRV yang stabil.")

    sig_bp = bandpass_filter(signal, args.sample_rate)
    sig_notch = notch_filter(sig_bp, args.sample_rate)
    sig_detrend = nk.signal_detrend(sig_notch)
    sig_clean = nk.standardize(sig_detrend)

    _, rpeaks_info = nk.ecg_peaks(sig_clean, sampling_rate=args.sample_rate)
    rpeaks = rpeaks_info["ECG_R_Peaks"]

    if len(rpeaks) < 3:
        raise ValueError("R-peaks tidak cukup untuk menghitung SDNN/RMSSD.")

    rr_intervals = np.diff(rpeaks) / args.sample_rate
    rr_mean = finite_or_none(np.mean(rr_intervals))
    bpm = finite_or_none(60 / rr_mean if rr_mean and rr_mean > 0 else None)

    hrv = nk.hrv_time(rpeaks, sampling_rate=args.sample_rate, show=False)
    sdnn = finite_or_none(hrv["HRV_SDNN"].values[0])
    rmssd = finite_or_none(hrv["HRV_RMSSD"].values[0])

    print(json.dumps({
        "sdnn": sdnn,
        "rmssd": rmssd,
        "rr_mean": rr_mean,
        "bpm": bpm,
        "r_peaks": len(rpeaks),
    }))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(json.dumps({"error": str(exc)}))
        sys.exit(1)
