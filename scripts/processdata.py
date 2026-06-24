import serial
import pandas as pd
import numpy as np
import json
import matplotlib.pyplot as plt
import matplotlib.image as mpimg
import paho.mqtt.client as mqtt
from collections import deque
from time import gmtime, strftime
from scipy.signal import butter, lfilter
from numpy import *
from pylab import *
from scipy import signal
from biosppy.signals import ecg
from biosppy import tools as st
from matplotlib import gridspec

class ProcessData():
    def __init__(self, **kwargs):
        self.dataSet = pd.read_csv(kwargs.get('dataSet'))
        self.data = self.dataSet.iloc[:, -1].values
        self.resultVar = dict()
        self.testResult = None
        self.valueVar = dict()
        self.MAJOR_LW = 2.5
        self.MINOR_LW = 1.5
        self.MAX_ROWS = 10
        self.subjectName = kwargs.get('subject_name')
        self.subjectId = kwargs.get('subject_id')
        self.intervalSignal = dict()
        self.client = mqtt.Client()
    
    def testData(self, **kwargs):
        sampling_rate = kwargs.get('sampling_rate')
        filtered = kwargs.get('filtered')
        testData = kwargs.get('testdata')
        rpeaks = kwargs.get('rpeaks')

        varName = kwargs.get('varname')

        varNameList = ['R', 'Q', 'S', 'P', 'P_awal', 'T', 'T_akhir']
        if varName not in varNameList:
            print("{0} not in list Test".format(varName))
            return

        testList = []
        idx = []
        arr = []
        intervalNum = kwargs.get('intervalnum')
        intervalr = int((sampling_rate/1000)*intervalNum)

        if varName == 'R':
            result = [filtered[i] for i in rpeaks]
            # self.testResult = result
            self.resultVar['R'] = result
            return result

        if varName == 'Q':
            testData = self.resultVar['R']
        elif varName == 'S':
            testData = self.resultVar['R']
        elif varName == 'P':
            testData = self.resultVar['Q']
        elif varName == 'P_awal':
            testData = self.resultVar['P']
        elif varName == 'T':
            testData = self.resultVar['S']
        elif varName == 'T_akhir':
            testData = self.resultVar['T']
        
        for x in testData:
            t = 0
            for y in filtered:
                if x!=y:
                    t+=1
                    continue
                
                if varName == 'Q':
                    nMin = t-intervalr
                    arr = filtered[nMin:t]
                    data = np.min(arr)
                    index = np.where(arr == data)
                    iindex = intervalr - index[0]
                    idx = np.append(idx, iindex).astype(int)
                    testList = np.append(testList, data)
                    t+=1
                    continue
                if varName == 'S':
                    nMax = t + intervalr + 1
                    arr = filtered[t+1:nMax]
                    data = np.min(arr)
                    index = np.where(arr == data)
                    iindex = intervalr - index[0]
                    iiindex = intervalr - iindex
                    idx = np.append(idx, iiindex).astype(int)
                    testList = np.append(testList, data)
                    t+=1
                    continue
                if varName == 'P':
                    nMin = t-intervalr
                    arr = filtered[nMin:t]
                    data = np.max(arr)
                    index = np.where(arr == data)
                    iindex = intervalr - index[0]
                    idx = np.append(idx, iindex).astype(int)
                    testList = np.append(testList, data)
                    t+=1
                    continue
                if varName == 'P_awal':
                    nMin = t-intervalr
                    arr = filtered[nMin:t]
                    data = np.min(arr)
                    index = np.where(arr == data)
                    iindex = intervalr - index[0]
                    idx = np.append(idx, iindex).astype(int)
                    testList = np.append(testList, data)
                    t+=1
                    continue
                if varName == 'T':
                    nMax = t + intervalr + 1
                    arr = filtered[t+1:nMax]
                    data = np.max(arr)
                    index = np.where(arr == data)
                    iindex = intervalr - index[0]
                    iiindex = intervalr - iindex
                    idx = np.append(idx, iiindex).astype(int)
                    testList = np.append(testList, data)
                    t+=1
                    continue
                if varName == 'T_akhir':
                    nMax = t + intervalr + 1
                    arr = filtered[t+1:nMax]
                    data = np.min(arr)
                    index = np.where(arr == data)
                    iindex = intervalr - index[0]
                    iiindex = intervalr - iindex
                    idx = np.append(idx, iiindex).astype(int)
                    testList = np.append(testList, data)
                    t+=1
                    continue
                    
        testVar = []
        if varName == 'Q':
            testVar = rpeaks - idx
        elif varName == 'S':
            testVar = rpeaks + idx
        elif varName == 'P':
            testVar = self.valueVar['Q'] - idx
        elif varName ==' P_awal':
            testVar = self.valueVar['P'] - idx
        elif varName == 'T':
            testVar = self.valueVar['S'] + idx
        elif varName == 'T_akhir':
            testVar = self.valueVar['T'] + idx

        result = [filtered[i] for i in testVar]
        self.valueVar[varName] = testVar
        self.resultVar[varName] = result
        # print(testVar)
        # print(result)
        print('cek {0} selesai'.format(varName))
        return result

    def showNormalSignal(self, **kwargs):
        #show normal signal
        image = mpimg.imread('normal_ecg.PNG')
        plt.imshow(image)
        plt.axis("off")
        plt.show(block=False)

    def getInterval(self, **kwargs):
        intervalName = kwargs.get('name')
        ts = kwargs.get('ts')
        if intervalName == 'QRS':
            interval = ts[self.valueVar['S']] - ts[self.valueVar['Q']]
        elif intervalName == 'PR':
            interval = ts[kwargs.get('rpeaks')] - ts[self.valueVar['P']]
        elif intervalName == 'QT':
            interval = ts[self.valueVar['T']] - ts[self.valueVar['Q']]
        elif intervalName == 'ST':
            interval = ts[self.valueVar['T']] - ts[self.valueVar['S']]
        else:
            interval = ts[self.valueVar['T_akhir']] - ts[self.valueVar['P']]
        
        result = sum(interval) / len(interval)
        print("Interval {0} : {1}".format(intervalName, result))
        self.intervalSignal[intervalName] = result
        return

    def calculateRRN(self, **kwargs):
        peaks = kwargs.get('peaks')
        n = kwargs.get('n')
        prevs = list()
        nexts = list()
        for i, peak in enumerate(peaks):            
            if i < len (peaks) - n:
                nextValue = peaks[i+n] - peak
            else:
                nextValue = 0

            if i < n:
                prevValue = 0
            else:
                prevValue = peak - peaks[i-n]

            prevs.append(prevValue)
            nexts.append(nextValue)
        
        return prevs, nexts

    def calculateRRIR(self, **kwargs):
        rrs = kwargs.get('rrs')
        n = kwargs.get('n')
        prevs = list()
        nexts = list()
        
        for i, rr in enumerate(rrs):
            if i < len (rrs) - n:
                if rrs[i+n] != 0:
                    ratio_n = rr / rrs[i+n]
                else:
                    ratio_n = 0
            else:
                ratio_n = 0
                
            if i < n:
                ratio_p = 0
            else: 
                if rrs[i-n] != 0:
                    ratio_p = rr /rrs[i-n]
                else:
                    ratio_p = 0
                
            prevs.append(ratio_p)
            nexts.append(ratio_n)
        return prevs, nexts    

    def calculateLocAVG(self, **kwargs):
        rrs = kwargs.get('rrs')
        n = kwargs.get('n')
        avg = np.convolve(rrs, np.ones((n,))/n, 'valid')
        
        if n != 1:
            # prepend and append zeros to make the shape is same as input
            avg = np.insert(avg, 0, np.zeros(math.floor(n/2)), axis=0)
            avg = np.append(avg, np.zeros(math.floor(n/2)-1))
        return avg

    def lnFeatures(self, **kwargs):
        df = kwargs.get('df')
        ln_col = kwargs.get('ln_col')
        df_mod = df.copy()
        for column in df_mod:
            if column in ln_col:
                df_mod[column] = df_mod[column].apply(lambda x: np.log(x) if x != 0 else x)
        return df_mod

    def process(self):
        sampling_rate = 230

        # ensure numpy
        signal = np.array(self.data)
        sampling_rate = float(sampling_rate)

        # filter signa ql
        order = int(0.3 * sampling_rate)
        filtered, _, _ = st.filter_signal(signal=signal,
            ftype='FIR',
            band='bandpass',
            order=order,
            frequency=[3, 45],
            sampling_rate=sampling_rate)

        # segment
        rpeaks = ecg.hamilton_segmenter(signal=filtered, sampling_rate=sampling_rate)

        # correct R-peak locations
        rpeaks, = ecg.correct_rpeaks(signal=filtered,
            rpeaks=rpeaks[0],
            sampling_rate=sampling_rate,
            tol=0.05)

        # extract templates
        templates, rpeaks = ecg.extract_heartbeats(signal=filtered,
            rpeaks=rpeaks,
            sampling_rate=sampling_rate,
            before=0.2,
            after=0.4)
        # compute heart rate
        hr_idx, heart_rate = st.get_heart_rate(beats=rpeaks,
            sampling_rate=sampling_rate,
            smooth=True,
            size=3)

        # get time vectors
        length = len(signal)
        T = (length - 1) / sampling_rate
        ts = np.linspace(0, T, length, endpoint=False)
        heart_rate_ts = ts[hr_idx]
        templates_ts = np.linspace(-0.2, 0.4, templates.shape[1], endpoint=False)

        valueDict = {}
        valueDict['rpeaks'] = rpeaks
        valueDict['filtered'] = filtered
        valueDict['sampling_rate'] = sampling_rate
        valueDict['testdata'] = self.testData

        varNameList = ['R', 'Q', 'S', 'P', 'P_awal', 'T', 'T_akhir']

        for var in varNameList:
            self.resultVar[var] = list()
        
        intervalNum = {
            'Q': 80,
            'S': 80,
            'P': 200,
            'P_awal': 40,
            'T': 440,
            'T_akhir': 80,
            'R': 0,
        }
        for var_name in varNameList:
            # if var_name != 'R' : continue
            valueDict['varname'] = var_name
            valueDict['intervalnum'] = intervalNum[var_name]
            # print(self.resultVar)
            self.testData(**valueDict)
            

        #coba plotting
        # Globals
        self.showNormalSignal()

        #define variable
        raw = signal
        path = None

        fig = plt.figure()
        fig.suptitle(self.subjectName)
        gs = gridspec.GridSpec(6, 2)


        #plot raw signal
        ax1 = fig.add_subplot(gs[:2, 0:])
        ax1.plot(ts, raw, linewidth=self.MAJOR_LW, label='Raw')
        ax1.set_ylabel('Amplitude')
        ax1.legend()
        ax1.grid()


        # filtered signal with rpeaks
        ymin = np.min(filtered)
        ymax = np.max(filtered)
        alpha = 0.1 * (ymax - ymin)
        ymax += alpha
        ymin -= alpha

        #plot PQRST
        ax2 = fig.add_subplot(gs[2:4, 0:])
        ax2.plot(ts, filtered, label='filtered')
        ax2.vlines(ts[rpeaks], ymin, ymax, color='m', linewidth=self.MINOR_LW, label='R-peaks')

        ax2.scatter(ts[self.valueVar['P']], self.resultVar['P'], color='c', label="P-dot")
        ax2.scatter(ts[self.valueVar['Q']], self.resultVar['Q'], color='g', label="Q-dot")
        ax2.scatter(ts[rpeaks], self.resultVar['R'], color='r', label='R-dot')
        
        ax2.scatter(ts[self.valueVar['S']], self.resultVar['S'], color='y', label="S-dot")
        ax2.scatter(ts[self.valueVar['T']], self.resultVar['T'], color='b', label="T-dot")
        ax2.scatter(ts[self.valueVar['P_awal']], self.resultVar['P_awal'], color='grey', label="P-T Intervals")
        ax2.scatter(ts[self.valueVar['T_akhir']], self.resultVar['T_akhir'], color='grey')

        ax2.set_ylabel('Amplitude')
        ax2.legend()
        ax2.grid()

        # rata rata BPM
        menit = T/60
        bpmratarata = (len(self.resultVar['R'])/menit)

        #plot heart rate
        ax3 = fig.add_subplot(gs[4:5, 0:])
        ax3.plot(heart_rate_ts, heart_rate, linewidth=self.MAJOR_LW, label='Heart Rate: {0} bpm'.format(bpmratarata))
        ax3.set_xlabel('Time (s)')
        ax3.set_ylabel('Heart Rate (bpm)')
        ax3.legend()
        ax3.grid()

        intervalNameList = ['QRS', 'PR', 'QT', 'ST', 'PT']
        parameterInterval = {}
        parameterInterval['ts'] = ts
        parameterInterval['rpeaks'] = rpeaks
        for interval_name in intervalNameList:
            parameterInterval['name'] = interval_name
            self.getInterval(**parameterInterval)

        #parameter PR
        ax4 = fig.add_subplot(gs[5:, 0:])

        #parameter PT
        ax4.text(0.4, 0.5, 'PT : {0:.3f}s'.format(self.intervalSignal['PT']),
                bbox={'facecolor': 'white', 'alpha': 0.5, 'pad': 3})
        if self.intervalSignal['PT'] >= 0.47 and self.intervalSignal['PT'] <= 0.64:
            status_pt = 'Normal'
            ax4.text(0.55, 0.5, '{0}'.format(status_pt),
                bbox={'facecolor': 'lime', 'alpha': 0.5, 'pad': 3})
        else:
            status_pt = 'Abnormal'
            ax4.text(0.55, 0.5, '{0}'.format(status_pt),
                bbox={'facecolor': 'red', 'alpha': 0.5, 'pad': 3})

        #parameter BPM
        ax4.text(0.05, 0.5, 'BPM : {0}'.format(bpmratarata),
                bbox={'facecolor': 'white', 'alpha': 0.5, 'pad': 3})
        print(bpmratarata)
        if bpmratarata >= 60 and bpmratarata <= 100:
            status_bpm = 'Normal'
            ax4.text(0.175, 0.5, '{0}'.format(status_bpm),
                bbox={'facecolor': 'lime', 'alpha': 0.5, 'pad': 3})
        else:
            status_bpm = 'Abnormal'
            ax4.text(0.175, 0.5, '{0}'.format(status_bpm),
                bbox={'facecolor': 'red', 'alpha': 0.5, 'pad': 3})


        rr1_p, rr1_n     = self.calculateRRN(peaks=rpeaks, n=1)
        rr2_p, rr2_n     = self.calculateRRN(peaks=rpeaks, n=2)
        rr3_p, rr3_n     = self.calculateRRN(peaks=rpeaks, n=3)
        rr4_p, rr4_n     = self.calculateRRN(peaks=rpeaks, n=4)

        rrir1_p, rrir1_n = self.calculateRRIR(rrs=rr1_n, n=1)
        rrir2_p, rrir2_n = self.calculateRRIR(rrs=rr1_n, n=2)
        rrir3_p, rrir3_n = self.calculateRRIR(rrs=rr1_n, n=3)
        rrir4_p, rrir4_n = self.calculateRRIR(rrs=rr1_n, n=4)
        
        loc_avg_10       = self.calculateLocAVG(rrs=rr1_n, n=1)
        
        rata2            = sum(rr1_p)/len(rr1_p)
        rata2r           = sum(rrir1_p)/len(rrir1_p)
        rata2lokal       = sum(loc_avg_10)/len(loc_avg_10)

        dict = {
                'rr1_p': rr1_p, 'rr1_n': rr1_n,
                'rr2_p': rr2_p, 'rr2_n': rr2_n,
                'rr3_p': rr3_p, 'rr3_n': rr3_n,
                'rr4_p': rr4_p, 'rr4_n': rr4_n,
                'rrir1_p': rrir1_p, 'rrir1_n': rrir1_n,
                'rrir2_p': rrir2_p, 'rrir2_n': rrir2_n,
                'rrir3_p': rrir3_p, 'rrir3_n': rrir3_n,
                'rrir4_p': rrir4_p, 'rrir4_n': rrir4_n,
                'loc_avg_10': loc_avg_10
                }
            
        # buat Pandas DataFrame berdasarkan dictionary
        df = pd.DataFrame(dict) 

        # make layout tight
        gs.tight_layout(fig)

        #IOT
        self.client.connect("localhost",1883)
        print("Client Connected!")

        # self.client.publish("building/subjek", self.subjectName)
        print("______________________________________________________")
        print('Subjek : {0}'.format(self.subjectName))
        print("______________________________________________________")
        print(" ")
        self.client.publish("building/pt", "{:.3f}".format(self.intervalSignal['PT']))
        print('PT : %.3f' %self.intervalSignal['PT'])
        self.client.publish("building/statuspt", status_pt)
        print('status PT : %s' %status_pt)
        print(" ")

        self.client.publish("building/tspt",self.intervalSignal['PT'])
        print("Interval PT Sent!")
        self.client.publish("building/bpm",bpmratarata)
        print("BPM Sent!")
        self.client.publish("building/RR",rata2r)
        print("Rata RR Sent!")
        self.client.publish("building/rrlokal",rata2lokal)
        print("RR Lokal Sent!")

        hrr=[]
        for jx in heart_rate:
            hrr.append(jx)
        json.dumps(hrr)
        hrrfix=str(hrr)
        self.client.publish("building/hrr",hrrfix)
        print("heart rate terkirim!")

        self.client.publish("subjectid", self.subjectId)

        for xx in heart_rate:
            self.client.publish("building/heartrate", xx)
        print("Heart Rate Sent!")

        ecg_signal = raw[0:1000]
        for x in ecg_signal:
            self.client.publish("building/rawdata", x)
        print("Rawdata Sent!")
        print('panjang Interval PT',self.intervalSignal['PT'])

        filt_signal = filtered[0:1000]
        for xxx in filt_signal:
            self.client.publish("building/filtered", xxx)
        print("Filtered Sent!")
        print("Jumlah Raw Data: %s    Terkirim: %s" %((len(raw)),(len(ecg_signal))))
        print("Jumlah Heart Rate: %s    Terkirim: %s" %((len(heart_rate)),(len(heart_rate))))
        print("Jumlah Filtered Signals: %s    Terkirim: %s" %((len(filtered)),(len(filt_signal))))


        # plt.show(block=False)

        # show time        
        # plt.show()

        raw_data = {
            'Nama Subjek':[self.subjectName],
            'Interval PT':[self.intervalSignal['PT']],
            'BPM':[bpmratarata],
            'Rata2 RR':[rata2r],
            'Rata RR Lokal':[rata2lokal],
            'Heart_Rate':[heart_rate],
            'Status':[status_bpm]
        }
