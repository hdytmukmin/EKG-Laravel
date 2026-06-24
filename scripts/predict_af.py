import argparse
import json
import pickle
import sys


FEATURE_COLUMNS = ["BPM", "RR_mean(s)", "HRV_SDNN", "HRV_RMSSD"]


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--model", required=True)
    parser.add_argument("--features-json", required=True)
    args = parser.parse_args()

    with open(args.model, "rb") as handle:
        model = pickle.load(handle)

    payload = json.loads(args.features_json)
    row = [[float(payload[column]) for column in FEATURE_COLUMNS]]

    try:
        import pandas as pd

        data = pd.DataFrame(row, columns=FEATURE_COLUMNS)
    except Exception:
        data = row

    label = str(model.predict(data)[0])
    confidence = None

    if hasattr(model, "predict_proba"):
        probabilities = model.predict_proba(data)[0]
        confidence = float(max(probabilities))

    print(json.dumps({
        "label": label,
        "confidence": confidence,
    }))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(json.dumps({"error": str(exc)}))
        sys.exit(1)
