import argparse
import json
import os
import sys


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
    confidence = probabilities.get(diagnosis)

    print(json.dumps({
        "label": diagnosis,
        "confidence": confidence,
        "overall_probabilities": probabilities,
        "total_segments": result.get("total_segments"),
        "segments": result.get("segments", []),
    }))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(json.dumps({"error": str(exc)}))
        sys.exit(1)
