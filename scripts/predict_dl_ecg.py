import argparse
import json
import os
import sys
import traceback


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--pipeline-dir", required=True)
    parser.add_argument("--input-json", required=True)
    args = parser.parse_args()

    pipeline_dir = os.path.abspath(args.pipeline_dir)
    if not os.path.isdir(pipeline_dir):
        raise FileNotFoundError(f"Folder pipeline model tidak ditemukan: {pipeline_dir}")

    sys.path.insert(0, pipeline_dir)

    from pipeline import predict_ecg

    with open(args.input_json, "r", encoding="utf-8") as handle:
        payload = json.load(handle)

    raw_signal = payload.get("raw_signal", [])
    sample_rate = float(payload.get("sample_rate", 200))

    result = predict_ecg(raw_signal, fs=sample_rate)
    diagnosis = str(result.get("overall_diagnosis", "PENDING_MODEL"))
    probabilities = result.get("overall_probabilities", {})
    is_non_af = diagnosis.strip().lower().replace("_", "-") in {"non-af", "nonaf", "normal"}
    label = "NON_AF" if is_non_af else "AF"
    binary_probabilities = {
        "NON_AF": float(probabilities.get("Non-AF", 0.0)),
        "AF": float(probabilities.get("Persistent AF", 0.0))
        + float(probabilities.get("Paroxysmal AF", 0.0)),
    }
    confidence = binary_probabilities.get(label)

    segments = result.get("segments", [])
    for segment in segments:
        segment["predicted_class"] = (
            "NON_AF" if str(segment.get("predicted_class", "")).strip() == "Non-AF" else "AF"
        )

    print(json.dumps({
        "label": label,
        "confidence": confidence,
        "overall_probabilities": binary_probabilities,
        "total_segments": result.get("total_segments"),
        "segments": segments,
    }))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(json.dumps({"error": str(exc), "traceback": traceback.format_exc()}))
        sys.exit(1)
