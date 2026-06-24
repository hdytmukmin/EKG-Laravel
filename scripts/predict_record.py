import argparse
import json
import pickle

import numpy as np


def main():
    parser = argparse.ArgumentParser(description="Predict EKG class from recordekg features.")
    parser.add_argument("--model", required=True)
    parser.add_argument("--tspt", required=True, type=float)
    parser.add_argument("--bpm", required=True, type=float)
    parser.add_argument("--irr", required=True, type=float)
    parser.add_argument("--irrlokal", required=True, type=float)
    args = parser.parse_args()

    with open(args.model, "rb") as handle:
        model = pickle.load(handle)

    features = np.array([args.tspt, args.bpm, args.irr, args.irrlokal]).reshape(1, -1)
    prediction = model.predict(features)
    value = prediction.tolist()[0] if hasattr(prediction, "tolist") else prediction[0]

    print(json.dumps({"prediction": str(value)}))


if __name__ == "__main__":
    main()
